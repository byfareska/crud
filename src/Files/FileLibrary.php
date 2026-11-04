<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * What is known of the files of a storage, besides their bytes: the name each was uploaded under, its kind, its size -
 * and the list of them to pick one again. One per storage (byfareska_crud.files.storages.<name>.library):
 *
 * - {@see Doctrine\DoctrineFileLibrary}: a table (the bundle's entity or the application's), searched by name;
 * - {@see StorageFileLibrary}: the storage itself, listed - no names, no search by them;
 * - {@see NoFileLibrary}: nothing; files are uploaded, never picked again;
 * - one of the application's: a DAM, an API - a service implementing this.
 *
 * The bytes are the storage's: a library only keeps what it knows of them.
 */
interface FileLibrary
{
    /**
     * Keeps what is known of a file just uploaded.
     *
     * @return StoredFile as it is kept
     */
    public function register(StoredFile $file): StoredFile;

    /** What is known of a file; null = nothing (a library without a table: a file it never saw). */
    public function find(FileReference $reference): ?StoredFile;

    /** A page of its files, the newest first; empty for one that cannot Browse. */
    public function browse(FileQuery $query): FilePage;

    /** Forgets a file whose bytes are being deleted. */
    public function remove(FileReference $reference): void;

    /**
     * @return list<LibraryCapability>
     */
    public function capabilities(): array;
}
