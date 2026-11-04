<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * Where a save of the form leads: the way back (CrudReturn::afterSave()), the add form again for the next record, the
 * edit form of the record saved or its details. AsCrud::$save (else byfareska_crud.form.save) says where "save" leads,
 * AsCrud::$afterSave (else byfareska_crud.form.save_buttons) names the buttons a form has besides it; CrudSaveReturn
 * says where they lead.
 */
enum AfterSave: string
{
    /** "Save and back to the list": CrudReturn::afterSave(). */
    case Return = 'return';

    /** "Save and add another": the add form, empty. */
    case New = 'new';

    /** "Save and keep editing": the edit form of the record - where "save" leads by default. */
    case Edit = 'edit';

    /** "Save and view": the details of the record. */
    case Show = 'show';
}
