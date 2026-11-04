<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\Component\FileLibrary;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileRemover;
use Byfareska\Crud\Files\FileUsage;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\Crud\Files\UploadProfile;
use PHPUnit\Framework\TestCase;

/**
 * The window of the library shows what the field it was opened for may pick, and deletes a file records use only
 * once that is confirmed.
 */
final class FileLibraryComponentTest extends TestCase
{
    private FilesKit $files;

    protected function setUp(): void
    {
        $this->files = new FilesKit();
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
