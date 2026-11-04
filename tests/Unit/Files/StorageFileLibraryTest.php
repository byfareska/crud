<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileLibrary;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use Byfareska\Crud\Files\StorageFileLibrary;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\Testing\FileLibraryContractTestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

/**
 * The library of a storage without a table: its files as the storage lists them.
 */
final class StorageFileLibraryTest extends FileLibraryContractTestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    protected function library(): FileLibrary
    {
        return new StorageFileLibrary(new FlysystemFileStorage($this->filesystem), self::STORAGE);
    }

    protected function put(StoredFile $file): void
    {
        // The in-memory adapter tells the type by the bytes, as finfo does.
        $this->filesystem->write($file->reference->path, match ($file->mimeType) {
            'image/png' => "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0",
            'application/pdf' => "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n",
            default => 'data',
        });
    }

    protected function removed(StoredFile $file): void
    {
        $this->filesystem->delete($file->reference->path);
    }
}
