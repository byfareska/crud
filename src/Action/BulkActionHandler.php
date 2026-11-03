<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * Makes the button of one kind of {@see BulkAction} for the records chosen, and does it: a service, found by its class
 * - the one the action names in BulkAction::handler(). Autoconfigured.
 *
 * A button that runs it on the server has the attributes of the live action "bulk" with the name of the action:
 * `'data-action' => 'live#action', 'data-live-action-param' => 'bulk', 'data-live-name-param' => $action->name`. One
 * that leads elsewhere (a link to an export with the ids) or has a component of its own needs no run().
 */
interface BulkActionHandler
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.bulk_action_handler';

    /**
     * The button in the bar over the list, drawn as ActionPlace::Bar; null = not offered for these records.
     */
    public function button(BulkAction $action, BulkSelection $selection): ?ActionButton;

    /**
     * Does it with the records chosen; what to tell (a toast), or null for nothing. The list forgets the choice then.
     */
    public function run(BulkAction $action, BulkSelection $selection): ?BulkResult;
}
