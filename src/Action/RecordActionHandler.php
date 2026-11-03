<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\RecordView;

/**
 * Makes the button of one kind of {@see RecordAction} for a record: a service, found by its class - the one the action
 * names in RecordAction::handler(). Autoconfigured.
 */
interface RecordActionHandler
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.record_action_handler';

    /**
     * @return ActionButton|null null = the action is not offered for this record (e.g. it has nothing to act on)
     */
    public function button(RecordAction $action, RecordView $record): ?ActionButton;
}
