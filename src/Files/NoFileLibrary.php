<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * A storage whose files are uploaded and shown, but never listed to be picked again (byfareska_crud.files.library:
 * none).
 */
final readonly class NoFileLibrary implements FileLibrary
{
    public function register(StoredFile $file): StoredFile
    {
        return $file;
    }

    public function find(FileReference $reference): ?StoredFile
    {
        return null;
    }

    public function browse(FileQuery $query): FilePage
    {
        return FilePage::empty();
    }

    public function remove(FileReference $reference): void
    {
    }

    public function capabilities(): array
    {
        return [];
    }
}
