<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * The colour of an action's button, by what it does, so one is known at a glance: adding is green, editing yellow,
 * deleting red, looking blue. The values are Bootstrap's colour names; every theme draws each its own way
 * (crud/actions.html.twig). An action of the application's own is Secondary unless it says otherwise.
 */
enum ActionTone: string
{
    /** The main thing to do where it is. */
    case Primary = 'primary';
    /** Anything else - the colour of an action that does not say. */
    case Secondary = 'secondary';
    /** Adds something: a new record, a copy. */
    case Success = 'success';
    /** Changes something: editing. */
    case Warning = 'warning';
    /** Destroys something: deleting. */
    case Danger = 'danger';
    /** Only shows something: the details. */
    case Info = 'info';
}
