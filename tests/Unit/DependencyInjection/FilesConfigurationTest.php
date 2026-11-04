<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\DependencyInjection;

use Byfareska\Crud\Controller\FilesController;
use Byfareska\Crud\CrudBundle;
use Byfareska\Crud\Files\Component\FileLibrary;
use Byfareska\Crud\Files\Doctrine\DoctrineFileLibrary;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileValueNormalizer;
use Byfareska\Crud\Files\Form\CrudFileType;
use Byfareska\Crud\Files\Form\FileTypeGuesser;
use Byfareska\Crud\Files\FileStorage;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\FileUploader;
use Byfareska\Crud\Files\NoFileLibrary;
use Byfareska\Crud\Files\PathGenerator;
use Byfareska\Crud\Files\RichTextFiles;
use Byfareska\Crud\Form\CrudRichTextType;
use Byfareska\Crud\Form\RichTextTypeGuesser;
use LogicException;
use Byfareska\Crud\Files\StorageFileLibrary;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Files are off until byfareska_crud.files is enabled: nothing of them is in the container before.
 */
final class FilesConfigurationTest extends TestCase
{
    public function testNothingOfFilesWithoutThem(): void
    {
        $builder = $this->load([]);

        self::assertFalse($builder->getParameter('byfareska_crud.files.enabled'));
        // What services->load() excludes stays as an abstract definition until the container is compiled.
        $definitions = array_filter(
            $builder->getDefinitions(),
            static fn (Definition $definition): bool => !$definition->hasTag('container.excluded'),
        );
        $files = array_filter(
            [...array_keys($definitions), ...array_keys($builder->getAliases())],
            static fn (string $id): bool => str_contains($id, '\\Files\\')
                || str_starts_with($id, 'byfareska_crud.files.')
                || $id === FilesController::class,
        );
        self::assertSame([], array_values($files));
    }

    public function testTheStoragesTheirLibrariesAndTheUploader(): void
    {
        $builder = $this->load(['files' => [
            'storages' => [
                'public' => ['service' => 'app.public_storage', 'public_url' => '/uploads'],
                'private' => ['service' => 'app.private_storage', 'url' => 'signed', 'library' => 'none'],
            ],
            'max_size' => '2M',
        ]]);
        foreach (['app.public_storage', 'app.private_storage'] as $id) {
            $builder->setDefinition($id, new Definition(Filesystem::class, [
                new Definition(InMemoryFilesystemAdapter::class),
            ]));
        }
        foreach ([CrudFileType::class, FileTypeGuesser::class, FileValueNormalizer::class, FileLibrary::class] as $id) {
            self::assertTrue($builder->hasDefinition($id), $id);
        }
        // The controller needs the whole framework (the router, Twig...): the example application runs it.
        $builder->removeDefinition(FilesController::class);
        foreach ([FileStorages::class, FileLibraries::class, FileUploader::class, PathGenerator::class] as $id) {
            $builder->findDefinition($id)->setPublic(true);
        }
        $builder->compile();

        self::assertTrue($builder->getParameter('byfareska_crud.files.enabled'));
        $storages = $builder->get(FileStorages::class);
        self::assertInstanceOf(FileStorages::class, $storages);
        self::assertSame(['public', 'private'], $storages->names());
        self::assertSame('public', $storages->default());
        self::assertInstanceOf(FileStorage::class, $storages->storage('private'));
        $libraries = $builder->get(FileLibraries::class);
        self::assertInstanceOf(FileLibraries::class, $libraries);
        self::assertInstanceOf(StorageFileLibrary::class, $libraries->of('public'));
        self::assertInstanceOf(NoFileLibrary::class, $libraries->of('private'));
    }

    public function testWithDoctrineTheLibraryIsKeptInTheDatabase(): void
    {
        $builder = $this->load(
            ['files' => ['storages' => ['public' => ['service' => 'app.storage']]]],
            ['DoctrineBundle' => DoctrineBundle::class],
        );

        $library = $builder->getDefinition('byfareska_crud.files.library.public');
        self::assertSame(DoctrineFileLibrary::class, $library->getClass());
        $fields = $library->getArgument(5);
        self::assertInstanceOf(Definition::class, $fields);
        self::assertSame([[]], $fields->getArguments(), 'No names of its own: those of AbstractFileRecord.');
    }

    public function testRichTextIsThereWithoutFiles(): void
    {
        $builder = $this->load([]);

        self::assertSame(
            ['editor' => 'tinymce', 'options' => [], 'files' => false],
            $builder->getParameter('byfareska_crud.rich_text'),
        );
        // A form type and a guesser of the bundle's: no files of their own, no packages but symfony/form.
        foreach ([CrudRichTextType::class, RichTextTypeGuesser::class] as $id) {
            self::assertTrue($builder->hasDefinition($id), $id);
            self::assertFalse($builder->getDefinition($id)->hasTag('container.excluded'), $id);
        }
        self::assertFalse(
            $builder->hasDefinition(RichTextFiles::class)
                && !$builder->getDefinition(RichTextFiles::class)->hasTag('container.excluded'),
        );
    }

    public function testRichTextUploadsWithFilesOnly(): void
    {
        $this->expectException(LogicException::class);

        $this->load(['rich_text' => ['files' => true]]);
    }

    public function testRichTextOfFiles(): void
    {
        $builder = $this->load([
            'files' => ['storages' => ['public' => ['service' => 'app.storage']]],
            'rich_text' => ['options' => ['license_key' => 'gpl'], 'files' => true],
        ]);

        self::assertTrue($builder->hasDefinition(RichTextFiles::class));
        self::assertEquals(
            ['editor' => 'tinymce', 'options' => ['license_key' => 'gpl'], 'files' => true],
            $builder->getParameter('byfareska_crud.rich_text'),
        );
    }

    public function testAStorageIsNotNamedAsAScheme(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['files' => ['storages' => ['https' => ['service' => 'app.storage']]]]);
    }

    public function testTheWebServerSendsOnlySignedFiles(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['files' => ['storages' => ['public' => [
            'service' => 'app.storage',
            'offload' => ['header' => 'X-Accel-Redirect', 'prefix' => '/_protected/'],
        ]]]]);
    }

    public function testTheWebServerSendsTheFilesOfAStorage(): void
    {
        $builder = $this->load(['files' => ['storages' => [
            'public' => ['service' => 'app.storage'],
            'private' => [
                'service' => 'app.private',
                'url' => 'signed',
                'offload' => ['header' => 'X-Accel-Redirect', 'prefix' => '/_protected/'],
            ],
        ]]]);

        /** @var array<string, array<string, mixed>> $settings */
        $settings = $builder->getDefinition(FileStorages::class)->getArgument(0);
        self::assertArrayNotHasKey('offload', $settings['public']);
        self::assertSame(['header' => 'X-Accel-Redirect', 'prefix' => '/_protected/'], $settings['private']['offload']);
    }

    public function testEnabledFilesNeedAStorage(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['files' => ['enabled' => true]]);
    }

    /**
     * @param array<string, mixed> $config of byfareska_crud
     * @param array<string, class-string> $bundles of the kernel
     */
    private function load(array $config, array $bundles = []): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.debug', false);
        $builder->setParameter('kernel.bundles', $bundles);
        $builder->setParameter('kernel.secret', 'test');
        $builder->setParameter('kernel.project_dir', sys_get_temp_dir());
        $extension = new CrudBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $builder->registerExtension($extension);
        $builder->loadFromExtension('byfareska_crud', $config);
        $extension->load($builder->getExtensionConfig('byfareska_crud'), $builder);

        return $builder;
    }
}
