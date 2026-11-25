<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * Says whether an action of a record (RecordAction, AsCrudPage) is for that record - what a RecordGuard says of the
 * five operations, for what else can be done with it: "approve" only a draft, "refund" only a paid order, "restore"
 * only one deleted. A service, autoconfigured.
 *
 * What it refuses is not offered (the button goes, wherever the record has its actions) and a page of AsCrudPage
 * answers 403. What runs an action of the application's own - its controller, a live action, a use case - asks
 * again: RecordGuards::assertActionAllowed($name, $record). A button is only a way to it.
 *
 * Of the built-in actions, details and edit go by their operations alone (RecordGuard). Deleting is asked of it as
 * "delete" (DeleteAction::NAME), whatever the DeleteAction is named: its button, the live actions that delete and each
 * record of a bulk delete (RecordDeletion) - a record it refuses stays, the others of a bulk delete go.
 */
interface RecordActionGuard
{
    public const string TAG = 'byfareska_crud.record_action_guard';

    /** The resource of a guard of every resource. */
    public const string EVERY = '*';

    /** The resource whose records it guards, by name; EVERY for all. */
    public function resource(): string;

    /**
     * What it needs of an item of the list, besides the columns.
     *
     * @return list<string>
     */
    public function fields(): array;

    /**
     * @param string $action the name of the action (RecordAction::$name, AsCrudPage::$name)
     */
    public function allowsAction(string $action, RecordView $record): bool;
}
