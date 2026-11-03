<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * An action (a RecordAction or a BulkAction) whose dialog asks for something before it is done - see ConfirmInput.
 * What comes with the live action is checked against it on the server (ConfirmInput::accepted()) before the action is
 * done: the browser could send anything, or nothing.
 */
interface AsksForInput
{
    /** What the dialog asks for; null = nothing, only "yes". */
    public ?ConfirmInput $input { get; }
}
