<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\RecordView;

/**
 * A record is about to be added or changed through the form of a resource, its form valid (dispatched on Symfony's
 * event dispatcher before the driver saves it). A listener may change what is saved - the data of the form: the entity
 * or the DTO - e.g. set its author; or refuse by throwing a {@see CrudFailure}: nothing is saved and its message is
 * shown at the field it names, else above the form. Dispatched in the transaction of the save (RecordTransaction).
 */
final readonly class RecordSaving
{
    /**
     * @param string|null $id of the record; null when it is added
     * @param mixed $data what the form made: the entity (doctrine), the DTO (dto)
     * @param RecordView|null $before the record as it was before the form changed it - every property, when the
     *     resource can read one (empty else); null when it is added. What a diff of an audit log compares the data with
     * @param string|null $then the button that saved: an AfterSave ("new", "edit"), the name of a CrudSaveButton
     *     ("send"); null = "save"
     */
    public function __construct(
        public AsCrud $crud,
        public ?string $id,
        public mixed $data,
        public ?RecordView $before = null,
        public ?string $then = null,
    ) {
    }
}
