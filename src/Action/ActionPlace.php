<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * Where an {@see ActionButton} is drawn; the value names the block of @CrudTheme/crud/actions.html.twig (action_row,
 * ...).
 */
enum ActionPlace: string
{
    /** A button with its icon on a row of the list ("inline"). */
    case Row = 'row';
    /** An item of the menu of a row of the list. */
    case Menu = 'menu';
    /** A button with its text among those of a record: the details page, the edit form, the preview drawer. */
    case Bar = 'bar';
}
