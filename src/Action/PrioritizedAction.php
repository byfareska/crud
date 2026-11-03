<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * A RecordAction that says where it stands among the actions of a record: lower first. Without it an action is 0 -
 * and those of the same priority keep their order: details, edit, those of "actions", the pages, delete.
 */
interface PrioritizedAction extends RecordAction
{
    public int $priority { get; }
}
