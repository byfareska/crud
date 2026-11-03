<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordView;

/**
 * A record was added or changed through the form of a resource (dispatched on Symfony's event dispatcher after the
 * save and its sections, before the redirect): for an audit log, a notification, a live feed.
 *
 * Still in the transaction of the save ({@see \Byfareska\Crud\Resource\RecordTransaction}): a listener that
 * throws undoes the save, and what it writes is kept with it. What must happen only once the record is kept (an
 * e-mail) is a message of a transport written in the same transaction, or work for kernel.terminate.
 */
final readonly class RecordSaved
{
    /**
     * @param string|null $id of the record; null when the driver could not tell the id of a new one
     * @param mixed $data what was saved: the entity (doctrine), the DTO (dto)
     * @param RecordView|null $before the record as it was before (see RecordSaving::$before); null when it was added
     * @param string|null $then the button that saved: an AfterSave ("new", "edit"), the name of a CrudSaveButton
     *     ("send"); null = "save"
     */
    public function __construct(
        public AsCrud $crud,
        public ?string $id,
        public bool $created,
        public mixed $data = null,
        public ?RecordView $before = null,
        public ?string $then = null,
    ) {
    }
}
