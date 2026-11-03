<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * Where a save of the form leads, by the button that saved: the way back (CrudReturn::afterSave()), the add form again
 * for the next record, or the edit form of the record saved. AsCrud::$afterSave names the buttons a form has besides
 * "save"; CrudSaveReturn says where they lead.
 */
enum AfterSave: string
{
    /** "Save": CrudReturn::afterSave(). */
    case Return = 'return';

    /** "Save and add another": the add form, empty. */
    case New = 'new';

    /** "Save and keep editing": the edit form of the record. */
    case Edit = 'edit';
}
