<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * A page of the files of a library.
 */
final readonly class FilePage
{
    /**
     * @param list<StoredFile> $files
     * @param int|null $total how many there are in all; null = not counted
     * @param bool $more whether there is a next page
     */
    public function __construct(
        public array $files,
        public int $page = 1,
        public ?int $total = null,
        public bool $more = false,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }
}
