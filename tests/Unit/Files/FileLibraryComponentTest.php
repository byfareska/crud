<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\Component\FileLibrary;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileRemover;
use Byfareska\Crud\Files\FileUsage;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\Crud\Files\NoFileLibrary;
use Byfareska\Crud\Files\NoFileUsages;
use Byfareska\Crud\Files\UploadProfile;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * The window of the library shows what the field it was opened for may pick, and deletes only what it shows - a file
 * records use only once that is confirmed, after the server asked.
 */
final class FileLibraryComponentTest extends TestCase
{
    private FilesKit $files;

    protected function setUp(): void
    {
        $this->files = new FilesKit();
    }

    public function testTheLiveComponentReadsItsPropsAsTheyAre(): void
    {
        $library = $this->library([]);
        $library->open($this->files->browser->libraryToken(new UploadProfile('public'), false));
        $library->search = 'invoice';
        $library->page = 2;
        $accessor = PropertyAccess::createPropertyAccessor();

        // As LiveComponents reads a prop: a method get…(), is…(), has…() or can…() of its name would win.
        foreach (new ReflectionClass($library)->getProperties() as $property) {
            if ($property->getAttributes(LiveProp::class) !== []) {
                self::assertSame(
                    $property->getValue($library),
                    $accessor->getValue($library, $property->getName()),
                    $property->getName(),
                );
            }
        }
    }

    public function testNothingUntilOpenedForAField(): void
    {
        $library = $this->library();

        self::assertSame([], $library->getStorages());
        self::assertSame([], $library->getFiles());

        $library->open('/?storage=public&_hash=forged');
        self::assertSame([], $library->getStorages(), 'Not a token of the server.');
    }

    public function testTheFilesOfTheKindsOfTheField(): void
    {
        $this->files->put('ab/a.png');
        $this->files->put('ab/b.pdf', "%PDF-1.4\n");
        $library = $this->library();

        $library->open($this->files->browser->libraryToken(UploadProfile::images('public'), true));

        self::assertSame(['public'], $library->getStorages());
        self::assertTrue($library->isMultiple());
        self::assertSame(['public://ab/a.png'], array_column($library->getFiles(), 'ref'));
        self::assertTrue($this->files->signer->verify(
            FileReference::parse('public://ab/a.png'),
            is_string($library->getFiles()[0]['sig'] ?? null) ? $library->getFiles()[0]['sig'] : '',
        ));
    }

    public function testOnlyTheStoragesTheOneLookingMayUse(): void
    {
        $library = $this->library();
        $profile = new UploadProfile('public', browse: ['private', 'archive']);
        $library->open($this->files->browser->libraryToken($profile, false));

        self::assertSame(['public'], $library->getStorages(), 'private is for ROLE_ADMIN; archive has no library.');

        $admin = new FilesKit(['ROLE_ADMIN']);
        $library = new FileLibrary($admin->browser, $admin->libraries, $this->remover($admin, []));
        $library->open($admin->browser->libraryToken(new UploadProfile('public', browse: ['private']), false));
        $library->show('private');

        self::assertSame(['public', 'private'], $library->getStorages());
        self::assertSame('private', $library->getCurrentStorage());
    }

    public function testAFileInUseIsDeletedOnceConfirmed(): void
    {
        $used = $this->files->put('ab/used.png');
        $library = $this->library([new FileUsage('product', '7', 'gallery')]);
        $library->open($this->files->browser->libraryToken(new UploadProfile('public'), true));

        $library->remove((string) $used);

        self::assertTrue($this->files->filesystem->fileExists('ab/used.png'));
        self::assertNotNull($library->inUse);
        self::assertEquals([new FileUsage('product', '7', 'gallery')], $library->getUsages());

        $library->remove((string) $used, confirmed: true);

        self::assertFalse($this->files->filesystem->fileExists('ab/used.png'));
        self::assertNull($library->inUse);
    }

    public function testAFileInUseIsDeletedOnlyAfterTheServerAskedAboutIt(): void
    {
        $used = $this->files->put('ab/used.png');
        $other = $this->files->put('ab/other.png');
        $library = $this->library([new FileUsage('product', '7', 'gallery')]);
        $library->open($this->files->browser->libraryToken(new UploadProfile('public'), true));

        // "Confirmed" by the browser alone: asked first.
        $library->remove((string) $used, confirmed: true);

        self::assertTrue($this->files->filesystem->fileExists('ab/used.png'));
        self::assertSame((string) $used, $library->confirming);

        // What was asked about is that file, not another.
        $library->remove((string) $other, confirmed: true);

        self::assertTrue($this->files->filesystem->fileExists('ab/other.png'));
        self::assertSame((string) $other, $library->confirming);

        $library->remove((string) $other, confirmed: true);

        self::assertFalse($this->files->filesystem->fileExists('ab/other.png'));
        self::assertSame('', $library->confirming);
    }

    public function testNotAPathOfTheStorageTheWindowDoesNotShow(): void
    {
        // Not an upload, not of the kind of the field: a backup next to the uploads.
        $this->files->filesystem->write('backups/db.sql', 'x');
        $library = $this->library();
        $library->open($this->files->browser->libraryToken(UploadProfile::images('public'), false));

        $library->remove('public://backups/db.sql', true);
        $library->remove('public://backups/db.sql', true);

        self::assertTrue($this->files->filesystem->fileExists('backups/db.sql'));
    }

    public function testNotAFileItsLibraryDoesNotKnow(): void
    {
        $files = new FilesKit(library: new NoFileLibrary());
        $remover = new FileRemover($files->storages, $files->libraries, new NoFileUsages());
        $library = new FileLibrary($files->browser, $files->libraries, $remover);
        $library->open($files->browser->libraryToken(new UploadProfile('public'), false));

        self::assertFalse($library->canRemove(), 'A library that cannot list its files deletes none.');

        $files->filesystem->write('backups/db.sql', 'x');
        $this->expectException(InvalidArgumentException::class);
        $remover->remove(FileReference::parse('public://backups/db.sql'), true);
    }

    public function testNotAFileOfAStorageOnlyToPickFrom(): void
    {
        $this->files->filesystem->write('old/a.png', 'data');
        $library = $this->library();
        $library->open($this->files->browser->libraryToken(new UploadProfile('public', browse: ['legacy']), false));
        $library->show('legacy');

        self::assertSame('legacy', $library->getCurrentStorage());
        self::assertSame(['legacy://old/a.png'], array_column($library->getFiles(), 'ref'), 'Picked from it.');
        self::assertFalse($library->canRemove());

        $library->remove('legacy://old/a.png', true);

        self::assertTrue($this->files->filesystem->fileExists('old/a.png'));
    }

    public function testNoHtmlNorSvgInTheWindowOfAFieldOfAnyKind(): void
    {
        $this->files->put('ab/a.png');
        $this->files->filesystem->write('ab/b.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $library = $this->library();

        $library->open($this->files->browser->libraryToken(new UploadProfile('public'), false));

        self::assertSame(['public://ab/a.png'], array_column($library->getFiles(), 'ref'));

        $library = $this->library();
        $library->open($this->files->browser->libraryToken(new UploadProfile('public', ['image/svg+xml']), false));

        self::assertSame(['public://ab/b.svg'], array_column($library->getFiles(), 'ref'));
    }

    public function testNotAFileOfAnotherStorage(): void
    {
        $this->files->filesystem->write('ab/x.png', 'data');
        $library = $this->library();
        $library->open($this->files->browser->libraryToken(new UploadProfile('public'), true));

        $library->remove('private://ab/x.png', confirmed: true);

        self::assertTrue($this->files->filesystem->fileExists('ab/x.png'));
    }

    /**
     * @param list<FileUsage> $usages of every file
     */
    private function library(array $usages = []): FileLibrary
    {
        return new FileLibrary($this->files->browser, $this->files->libraries, $this->remover($this->files, $usages));
    }

    /**
     * @param list<FileUsage> $usages
     */
    private function remover(FilesKit $files, array $usages): FileRemover
    {
        $index = new readonly class ($usages) implements FileUsages {
            /** @param list<FileUsage> $usages */
            public function __construct(private array $usages)
            {
            }

            public function tracks(): bool
            {
                return true;
            }

            public function replace(string $resource, string $id, string $field, array $references): void
            {
            }

            public function forget(string $resource, string $id): void
            {
            }

            public function of(FileReference $reference): array
            {
                return $this->usages;
            }

            public function used(array $references): array
            {
                return $this->usages === [] ? [] : $references;
            }
        };

        return new FileRemover($files->storages, $files->libraries, $index);
    }
}
