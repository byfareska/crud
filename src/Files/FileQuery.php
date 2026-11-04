<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * What the window of a library asks for: a page of the files of a storage, the newest first.
 */
final readonly class FileQuery
{
    /**
     * @param string $search what was typed; "" = everything
     * @param list<string> $accept the kinds wanted, as UploadProfile::$accept; empty = any
     * @param int $page from 1
     */
    public function __construct(
        public string $storage,
        public string $search = '',
        public array $accept = [],
        public int $page = 1,
        public int $perPage = 24,
    ) {
    }

    public function offset(): int
    {
        return max(0, $this->page - 1) * $this->perPage;
    }

    /** Whether a file is of a kind wanted. */
    public function wants(StoredFile $file): bool
    {
        return new UploadProfile($this->storage, $this->accept)->acceptsFile($file);
    }
}
