<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Event\RecordChanged;
use Byfareska\Crud\Event\RecordChanging;
use Closure;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Changes a record otherwise than by its form - an action of a record (RunAction: "approve", "archive"), a toggle on
 * a row of the list, one record of a bulk action, a controller or a message handler of the application - the way the
 * form and the delete do it: all in a transaction (RecordTransaction), the record read in its scopes (also for one
 * who may not read it; a resource whose source cannot read a record leaves that to the work), its guards
 * (RecordGuard of the operation, RecordActionGuard of the action), RecordChanging (a listener may refuse), the work,
 * RecordChanged (given what the record was). An audit log listening to RecordSaved and RecordChanged sees every change.
 *
 *     $changes->change($crud, $id, 'archive', function (RecordView $record) use ($id): void {
 *         $this->products->find($id)?->archive();
 *     });
 */
final readonly class RecordChanges
{
    public function __construct(
        private CrudRecords $records,
        private RecordGuards $guards,
        private RecordTransaction $transaction = new NoRecordTransaction(),
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @template T
     *
     * @param string $action what is done, as its guards and listeners know it: the name of the action
     * @param Closure(RecordView): T $work given the record as it is before
     * @param string|null $input what was typed or chosen in the dialog that asked (ConfirmInput), for the listeners
     * @param CrudOperation|null $operation whose guards are asked besides those of the action (Update: what may not
     *     be edited, may not be changed otherwise either); null = only those of the action
     *
     * @return T what the work returns
     *
     * @throws CrudFailure the record is not there (404), a guard or a listener refused, or the work did
     */
    public function change(
        AsCrud $crud,
        string $id,
        string $action,
        Closure $work,
        ?string $input = null,
        ?CrudOperation $operation = CrudOperation::Update,
        RecordScreen $screen = RecordScreen::Details,
    ): mixed {
        return $this->transaction->run(
            $crud,
            function () use ($crud, $id, $action, $work, $input, $operation, $screen): mixed {
                // Read and guarded in the transaction it is changed in: what a guard goes by is what the change finds.
                $before = $this->records->view($crud, $id, $screen);
                // Also when the one changing it may not read it (a role, refusing()): the id comes from the browser
                // (an argument of a live action), and a record out of the scopes of the resource - of another tenant -
                // or not there at all is no record to change (404). The guards and the listeners go by what it is.
                if ($before->item === [] && $crud->source->supports(CrudOperation::Read)) {
                    $before = new RecordView($crud, $id, $this->records->read($crud, $id), $screen);
                }
                if ($operation instanceof CrudOperation) {
                    $this->guards->assertAllowed($operation, $before);
                }
                $this->guards->assertActionAllowed($action, $before);
                $this->events?->dispatch(new RecordChanging($before, $action, $input));
                $result = $work($before);
                $this->events?->dispatch(new RecordChanged($crud, $id, $action, $before, $input));

                return $result;
            },
        );
    }
}
