<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use DateTimeImmutable;

/**
 * Where the bytes of the files of one storage (byfareska_crud.files.storages.<name>) are: a disk, FTP, S3... The
 * bundle has the one of Flysystem ({@see Flysystem\FlysystemFileStorage}, made of a FilesystemOperator by itself);
 * a service of another kind - an API of images, a DAM - implements this interface and is named as the "service" of
 * the storage.
 *
 * Paths are those of {@see FileReference::$path}. A failure is a {@see FileStorageFailed}.
 */
interface FileStorage
{
    /**
     * Writes the file, creating what directories it needs.
     *
     * @param resource $contents read from where it is
     */
    public function write(string $path, mixed $contents, ?string $mimeType = null): void;

    /**
     * @return resource
     */
    public function readStream(string $path): mixed;

    public function exists(string $path): bool;

    /** Nothing happens when there is no such file. */
    public function delete(string $path): void;

    /**
     * The files under a directory and all those below it.
     *
     * @return iterable<StorageEntry>
     */
    public function list(string $directory = ''): iterable;

    /** What is known of one file; null when there is none. */
    public function entry(string $path): ?StorageEntry;

    /** An address anyone can open; null = the storage has none (a private one). */
    public function publicUrl(string $path): ?string;

    /** An address that works until then (S3's presigned); null = the storage cannot make one. */
    public function temporaryUrl(string $path, DateTimeImmutable $expiresAt): ?string;
}
