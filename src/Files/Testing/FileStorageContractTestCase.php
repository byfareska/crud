<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Testing;

use Byfareska\Crud\Files\FileStorage;
use Byfareska\Crud\Files\StorageEntry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * What every FileStorage does, for a storage of the application to be tested against: a test of it extends this one
 * and gives an empty storage. The bundle's own storages are tested by it too.
 *
 *     final class DamStorageTest extends FileStorageContractTestCase
 *     {
 *         protected function storage(): FileStorage
 *         {
 *             return new DamStorage(new FakeDamClient());
 *         }
 *     }
 */
abstract class FileStorageContractTestCase extends TestCase
{
    /** An empty storage, a new one for each test. */
    abstract protected function storage(): FileStorage;

    public function testWhatIsWrittenIsReadBack(): void
    {
        $storage = $this->storage();
        $storage->write('ab/cd/file.txt', $this->stream('Hello'), 'text/plain');

        self::assertTrue($storage->exists('ab/cd/file.txt'));
        self::assertSame('Hello', stream_get_contents($storage->readStream('ab/cd/file.txt')));
    }

    public function testNoSuchFile(): void
    {
        $storage = $this->storage();

        self::assertFalse($storage->exists('no/such.txt'));
        self::assertNull($storage->entry('no/such.txt'));
        $storage->delete('no/such.txt');
    }

    public function testADeletedFileIsGone(): void
    {
        $storage = $this->storage();
        $storage->write('a.txt', $this->stream('a'));
        $storage->delete('a.txt');

        self::assertFalse($storage->exists('a.txt'));
    }

    public function testItListsTheFilesBelowADirectory(): void
    {
        $storage = $this->storage();
        $storage->write('ab/cd/one.txt', $this->stream('1'));
        $storage->write('ab/ef/two.txt', $this->stream('22'));
        $storage->write('xy/three.txt', $this->stream('333'));

        $paths = array_map(static fn (StorageEntry $entry): string => $entry->path, [...$storage->list('ab')]);
        sort($paths);

        self::assertSame(['ab/cd/one.txt', 'ab/ef/two.txt'], $paths);
        self::assertCount(3, [...$storage->list()]);
    }

    public function testWhatItKnowsOfAFile(): void
    {
        $storage = $this->storage();
        $storage->write('ab/file.txt', $this->stream('Hello'), 'text/plain');

        $entry = $storage->entry('ab/file.txt');

        self::assertInstanceOf(StorageEntry::class, $entry);
        self::assertSame('ab/file.txt', $entry->path);
        self::assertContains($entry->size, [null, 5]);
    }

    public function testAnAddressIsTextOrNone(): void
    {
        $storage = $this->storage();
        $storage->write('a.txt', $this->stream('a'));

        $public = $storage->publicUrl('a.txt');
        $temporary = $storage->temporaryUrl('a.txt', new DateTimeImmutable('+1 hour'));

        self::assertTrue($public === null || $public !== '');
        self::assertTrue($temporary === null || $temporary !== '');
    }

    /**
     * @return resource
     */
    protected function stream(string $contents): mixed
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
