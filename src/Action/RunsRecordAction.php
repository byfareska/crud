<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\RecordView;

/**
 * A {@see RecordActionHandler} whose action is done on the server (RecordActions::run(), the live action "run" of
 * the list, Crud:Run) - not only drawn. The bundle's: RunActionHandler, for a RunAction.
 */
interface RunsRecordAction
{
    /**
     * Does the action to the record - called in the transaction of RecordChanges, its guards asked.
     *
     * @param string|null $input what its dialog asked for, checked (AsksForInput)
     *
     * @throws CrudFailure
     */
    public function run(RecordAction $action, RecordView $record, ?string $input): ?BulkResult;

    /** Whether the guards of Update are asked too, besides those of the action. */
    public function updates(RecordAction $action): bool;
}
