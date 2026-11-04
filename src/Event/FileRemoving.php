<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileUsage;

/**
 * A file is about to be deleted from its storage and forgotten by its library - from the window of the library, or
 * by byfareska:crud:files:prune. A listener may keep it (keep()): a file the application still needs.
 */
final class FileRemoving
{
    public bool $kept = false;

    /**
     * @param list<FileUsage> $usages where the index says it is used (deleting such a file was confirmed)
     */
    public function __construct(
        public readonly FileReference $reference,
        public readonly array $usages = [],
    ) {
    }

    public function keep(): void
    {
        $this->kept = true;
    }
}
