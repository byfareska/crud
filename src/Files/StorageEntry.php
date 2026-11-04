<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use DateTimeImmutable;

/**
 * A file as a storage knows it: no name it was uploaded under, no size of an image - what a library without a
 * database has to go by.
 */
final readonly class StorageEntry
{
    public function __construct(
        public string $path,
        public ?int $size = null,
        public ?string $mimeType = null,
        public ?DateTimeImmutable $lastModified = null,
    ) {
    }
}
