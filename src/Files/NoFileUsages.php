<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * No index of where files are used (byfareska_crud.files.usages: none, the default without Doctrine): every file
 * may be used somewhere, so none is pruned, and deleting one from a library warns that a record may still show it.
 */
final readonly class NoFileUsages implements FileUsages
{
    public function tracks(): bool
    {
        return false;
    }

    public function replace(string $resource, string $id, string $field, array $references): void
    {
    }

    public function forget(string $resource, string $id): void
    {
    }

    public function of(FileReference $reference): array
    {
        return [];
    }

    public function used(array $references): array
    {
        return $references;
    }
}
