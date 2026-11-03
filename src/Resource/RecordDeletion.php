<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * Deletes a record the way every place does it - the list, a page of the record, the bulk delete. The bundle's is
 * {@see DefaultRecordDeletion}: the resource can, its guards allow, RecordDeleting (a listener may refuse), the driver,
 * RecordDeleted. Decorate it (#[AsDecorator(RecordDeletion::class)]) to do more around it - a transaction, an archive
 * kept before - or alias it to delete another way (marking the record deleted instead).
 */
interface RecordDeletion
{
    /**
     * @throws CrudFailure the resource cannot delete, a guard or a listener refused, or the driver could not
     */
    public function delete(AsCrud $crud, string $id, RecordScreen $screen): void;
}
