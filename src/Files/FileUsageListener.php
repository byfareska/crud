<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Event\RecordDeleted;
use Byfareska\Crud\Event\RecordSaved;

/**
 * Keeps the index of where files are used (FileUsages): a record saved uses the files its fields hold now, in place of
 * those they held; a record deleted uses none. Within the transaction of the save (RecordTransaction).
 */
final readonly class FileUsageListener
{
    public function __construct(
        private SubmittedFiles $submitted,
        private FileUsages $usages,
    ) {
    }

    public function saved(RecordSaved $event): void
    {
        $fields = $this->submitted->take();
        // A new record whose id the driver could not tell: byfareska:crud:files:reindex finds it later.
        if ($event->id === null || !$this->usages->tracks()) {
            return;
        }
        foreach ($fields as $field => $references) {
            $this->usages->replace($event->crud->name, $event->id, $field, $references);
        }
    }

    public function deleted(RecordDeleted $event): void
    {
        $this->usages->forget($event->crud->name, $event->id);
    }
}
