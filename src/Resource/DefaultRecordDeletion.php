<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Action\DeleteAction;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Event\RecordDeleted;
use Byfareska\Crud\Event\RecordDeleting;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Deletes a record the way every place does it, all in a transaction (RecordTransaction): the record read, its guards
 * (RecordGuard of Delete; RecordActionGuard of the action "delete", DeleteAction::NAME, whatever the DeleteAction
 * offering it is named and for each record of a bulk delete), RecordDeleting (a listener may refuse), the driver,
 * RecordDeleted (given what the record was).
 */
final readonly class DefaultRecordDeletion implements RecordDeletion
{
    public function __construct(
        private CrudRecords $records,
        private RecordGuards $guards,
        private RecordTransaction $transaction = new NoRecordTransaction(),
        private ?EventDispatcherInterface $events = null,
        private ?ConfirmationInput $input = null,
    ) {
    }

    /**
     * @throws CrudFailure the resource cannot delete, a guard or a listener refused, or the driver could not
     */
    public function delete(AsCrud $crud, string $id, RecordScreen $screen): void
    {
        if (!$crud->can(CrudOperation::Delete)) {
            throw CrudFailure::forbidden();
        }

        // Read and guarded in the transaction it is deleted in: what a guard goes by ("not a paid invoice") is
        // what the delete finds, not what it was a moment before.
        $this->transaction->run($crud, function () use ($crud, $id, $screen): void {
            $record = $this->records->view($crud, $id, $screen);
            // The guards and the listeners go by the record also when the one deleting may not read it (a role).
            if ($record->item === [] && $crud->source->supports(CrudOperation::Read)) {
                $record = new RecordView($crud, $id, $this->records->read($crud, $id), $screen);
            }
            $this->guards->assertAllowed(CrudOperation::Delete, $record);
            // The delete button of a record is not offered for what a guard of its action refuses: neither is a
            // delete by the address of a live action, nor one record of a bulk delete.
            $this->guards->assertActionAllowed(DeleteAction::NAME, $record);
            $input = $this->input?->get();
            $this->events?->dispatch(new RecordDeleting($record, $input));
            $this->records->delete($crud, $id);
            $this->events?->dispatch(new RecordDeleted($crud, $id, $record, $input));
        });
    }
}
