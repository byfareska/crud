<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * A file in a field: its reference and what the field says of it there - "alt", "caption" - in the order of the field.
 * What a field of files exchanges with a FileBinding.
 */
final readonly class FileItem
{
    /**
     * @param array<string, string> $meta by name: "alt", "caption"
     */
    public function __construct(
        public FileReference $reference,
        public array $meta = [],
    ) {
    }

    /** What it says of itself, or "" */
    public function meta(string $name): string
    {
        return $this->meta[$name] ?? '';
    }
}
