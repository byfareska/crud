<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Testing;

use Byfareska\Crud\Files\FileLibrary;
use Byfareska\Crud\Files\FileQuery;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\LibraryCapability;
use Byfareska\Crud\Files\StoredFile;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * What every FileLibrary does, for a library of the application to be tested against: a test of it extends this one
 * and gives an empty library of the storage STORAGE. What a library cannot do (capabilities()) is not asked of it.
 * A library that reads its files from the storage itself writes their bytes in put().
 */
abstract class FileLibraryContractTestCase extends TestCase
{
    protected const string STORAGE = 'public';

    /** An empty library of STORAGE, a new one for each test. */
    abstract protected function library(): FileLibrary;

    /**
     * Before a file is registered: a library that lists its storage writes it there.
     */
    protected function put(StoredFile $file): void
    {
    }

    public function testItSaysWhatItCanDo(): void
    {
        self::assertContainsOnlyInstancesOf(LibraryCapability::class, $this->library()->capabilities());
    }

    public function testARegisteredFileIsTheSameFile(): void
    {
        $file = $this->file('ab/cd/photo.png', 'Photo.png', 'image/png');
        $this->put($file);

        $kept = $this->library()->register($file);

        self::assertTrue($kept->reference->equals($file->reference));
    }

    public function testARegisteredFileIsFound(): void
    {
        $library = $this->library();
        if (!$this->can($library, LibraryCapability::Browse)) {
            self::assertNull($library->find(FileReference::parse(static::STORAGE . '://no/such.png')));

            return;
        }
        $file = $this->register($library, 'ab/cd/photo.png', 'Photo.png', 'image/png');

        $found = $library->find($file->reference);

        self::assertInstanceOf(StoredFile::class, $found);
        self::assertTrue($found->reference->equals($file->reference));
        self::assertNull($library->find(FileReference::parse(static::STORAGE . '://no/such.png')));
    }

    public function testItListsItsFilesByKind(): void
    {
        $library = $this->library();
        if (!$this->can($library, LibraryCapability::Browse)) {
            self::assertSame([], $library->browse(new FileQuery(static::STORAGE))->files);

            return;
        }
        $this->register($library, 'ab/one.png', 'One.png', 'image/png', '-2 days');
        $this->register($library, 'ab/two.pdf', 'Two.pdf', 'application/pdf', '-1 day');

        $all = $this->paths($library->browse(new FileQuery(static::STORAGE))->files);
        $images = $this->paths($library->browse(new FileQuery(static::STORAGE, accept: ['image/*']))->files);
        sort($all);

        self::assertSame(['ab/one.png', 'ab/two.pdf'], $all);
        self::assertSame(['ab/one.png'], $images);
    }

    public function testItListsAPageAtATime(): void
    {
        $library = $this->library();
        if (!$this->can($library, LibraryCapability::Browse)) {
            self::assertFalse($library->browse(new FileQuery(static::STORAGE))->more);

            return;
        }
        foreach (['a', 'b', 'c'] as $i => $name) {
            $this->register($library, $name . '.txt', $name . '.txt', 'text/plain', sprintf('-%d minutes', 10 - $i));
        }

        $first = $library->browse(new FileQuery(static::STORAGE, page: 1, perPage: 2));
        $second = $library->browse(new FileQuery(static::STORAGE, page: 2, perPage: 2));

        self::assertCount(2, $first->files);
        self::assertTrue($first->more);
        self::assertCount(1, $second->files);
        self::assertFalse($second->more);
    }

    public function testItFindsByWhatIsTyped(): void
    {
        $library = $this->library();
        if (!$this->can($library, LibraryCapability::Search)) {
            self::markTestSkipped('The library cannot search.');
        }
        $this->register($library, 'ab/invoice-october.pdf', 'invoice-october.pdf', 'application/pdf');
        $this->register($library, 'ab/photo.png', 'photo.png', 'image/png');

        $found = $this->paths($library->browse(new FileQuery(static::STORAGE, 'invoice'))->files);

        self::assertSame(['ab/invoice-october.pdf'], $found);
    }

    public function testARemovedFileIsForgotten(): void
    {
        $library = $this->library();
        if (!$this->can($library, LibraryCapability::Remove) || !$this->can($library, LibraryCapability::Browse)) {
            self::markTestSkipped('The library cannot list or remove files.');
        }
        $file = $this->register($library, 'ab/photo.png', 'photo.png', 'image/png');

        $library->remove($file->reference);
        $this->removed($file);

        self::assertNull($library->find($file->reference));
    }

    /**
     * After a file is removed from the library: a library that lists its storage deletes it there, as FileRemover
     * does.
     */
    protected function removed(StoredFile $file): void
    {
    }

    protected function file(string $path, string $name, string $mimeType, string $created = 'now'): StoredFile
    {
        return new StoredFile(
            new FileReference(static::STORAGE, $path),
            $name,
            $mimeType,
            4,
            createdAt: new DateTimeImmutable($created),
        );
    }

    private function register(
        FileLibrary $library,
        string $path,
        string $name,
        string $mimeType,
        string $created = 'now',
    ): StoredFile {
        $file = $this->file($path, $name, $mimeType, $created);
        $this->put($file);

        return $library->register($file);
    }

    private function can(FileLibrary $library, LibraryCapability $capability): bool
    {
        return in_array($capability, $library->capabilities(), true);
    }

    /**
     * @param list<StoredFile> $files
     * @return list<string>
     */
    private function paths(array $files): array
    {
        return array_map(static fn (StoredFile $file): string => $file->reference->path, $files);
    }
}
