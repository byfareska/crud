<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * A {@see CrudReturn} that also says where the other buttons of a form lead (AfterSave: "save and add another", "save
 * and keep editing"). The bundle's DefaultCrudReturn is one; a CrudReturn of the application's own that is not one
 * sends every save to afterSave().
 */
interface CrudSaveReturn extends CrudReturn
{
    /**
     * @param string|null $id the record saved (of a new one: if the driver could tell)
     */
    public function afterSaveTo(AsCrud $crud, ?string $id, bool $created, AfterSave $then): string;
}
