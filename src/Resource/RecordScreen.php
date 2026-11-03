<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * Where a record is shown: what its actions and guards are asked for.
 */
enum RecordScreen: string
{
    /** A row of the list (and a record found by the command palette). */
    case List = 'list';
    /** The preview drawer of the list. */
    case Preview = 'preview';
    /** The details page, and the other pages about the record (#[AsCrudPage], #[MapCrud]). */
    case Details = 'details';
    /** The edit form. */
    case Form = 'form';
}
