<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * What a bulk action tells when it is done: a toast, its texts translated already - or, with $redirect, a page to go to
 * (the progress of a job sent to a queue, the file made), with the title as the toast shown there.
 */
final readonly class BulkResult
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $redirect = null,
    ) {
    }
}
