<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\DependencyInjection;

use Byfareska\Crud\CrudBundle;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

final class DoctrineMappingTest extends TestCase
{
    public function testTheTableOfTheLayoutsIsMappedInTheOnlyManager(): void
    {
        $orm = $this->prepended(['orm' => ['mappings' => []]]);

        self::assertArrayHasKey('ByfareskaCrud', $orm['mappings'] ?? []);
    }

    public function testTheTableOfTheLayoutsIsMappedInTheDefaultOfNamedManagers(): void
    {
        $orm = $this->prepended(['orm' => [
            'default_entity_manager' => 'main',
            'entity_managers' => ['legacy' => [], 'main' => []],
        ]]);

        self::assertArrayNotHasKey('mappings', $orm);
        self::assertSame(['main'], array_keys($orm['entity_managers'] ?? []));
        self::assertArrayHasKey('ByfareskaCrud', $orm['entity_managers']['main']['mappings'] ?? []);
    }

    public function testWithoutADefaultNamedTheFirstManagerIsIt(): void
    {
        $orm = $this->prepended(['orm' => ['entity_managers' => ['shop' => [], 'audit' => []]]]);

        self::assertSame(['shop'], array_keys($orm['entity_managers'] ?? []));
    }

    public function testNoTablesOfFilesUntilFilesAreEnabled(): void
    {
        $storages = ['storages' => ['public' => ['service' => 'app.storage']]];

        foreach ([[], ['files' => false], ['files' => ['enabled' => false] + $storages]] as $crud) {
            self::assertSame(['ByfareskaCrud'], array_keys($this->prepended([], $crud)['mappings'] ?? []));
        }
    }

    public function testTheTablesOfFilesWhenTheyAreKeptByDoctrine(): void
    {
        $orm = $this->prepended([], ['files' => ['storages' => ['public' => ['service' => 'app.storage']]]]);

        self::assertSame(
            ['ByfareskaCrud', 'ByfareskaCrudFilesMapped', 'ByfareskaCrudFilesRecord', 'ByfareskaCrudFilesUsage'],
            array_keys($orm['mappings'] ?? []),
        );
    }

    public function testNoTableOfTheBundleForAnEntityOfTheApplication(): void
    {
        $orm = $this->prepended([], ['files' => [
            'storages' => ['public' => ['service' => 'app.storage']],
            'entity' => 'App\\Entity\\Media',
            'usages' => 'none',
        ]]);

        self::assertSame(['ByfareskaCrud', 'ByfareskaCrudFilesMapped'], array_keys($orm['mappings'] ?? []));
    }

    public function testNoTablesOfFilesForLibrariesOfTheStorages(): void
    {
        $orm = $this->prepended([], ['files' => [
            'storages' => [
                'public' => ['service' => 'app.storage', 'library' => 'storage'],
                'private' => ['service' => 'app.private', 'library' => 'none'],
            ],
            'usages' => 'none',
        ]]);

        self::assertSame(['ByfareskaCrud'], array_keys($orm['mappings'] ?? []));
    }

    /**
     * The "orm" part of what the bundle prepends to the configuration of DoctrineBundle.
     *
     * @param array<string, mixed> $doctrine
     * @param array<string, mixed>|null $crud the configuration of byfareska_crud; null = none
     *
     * @return array{
     *     mappings?: array<string, mixed>,
     *     entity_managers?: array<string, array{mappings?: array<string, mixed>}>,
     * }
     */
    private function prepended(array $doctrine, ?array $crud = null): array
    {
        // What the kernel sets: the configurator of a bundle reads the environment (symfony/dependency-injection 8.0).
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->registerExtension(new DoctrineExtension());
        $builder->loadFromExtension('doctrine', $doctrine);
        $extension = new CrudBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $builder->registerExtension($extension);
        if ($crud !== null) {
            $builder->loadFromExtension('byfareska_crud', $crud);
        }

        $extension->prepend($builder);

        $orm = $builder->getExtensionConfig('doctrine')[0]['orm'] ?? null;
        self::assertIsArray($orm);

        /** @var array{mappings?: array<string, mixed>, entity_managers?: array<string, array{mappings?: array<string, mixed>}>} $orm */
        return $orm;
    }
}
