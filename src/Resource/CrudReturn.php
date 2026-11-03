<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * Where the pages of a record lead: their "back" button, the page after a save and the page after a delete. The
 * bundle's is {@see DefaultCrudReturn} (the list, or the list a CrudListedIn resource is shown in, filtered by the
 * record; one's own record for an "own" one). To go elsewhere - the edit form again after a save, the details of a new
 * record, the page of a parent - alias this interface to a service of the application, or decorate it
 * (#[AsDecorator(CrudReturn::class)]) and change only some resources.
 */
interface CrudReturn
{
    /**
     * The "back" of the pages of a record.
     *
     * @param string|null $id the record; null = none (a new one not saved)
     */
    public function url(AsCrud $crud, ?string $id = null): string;

    /**
     * Where the form goes once the record (and its sections) is saved.
     *
     * @param string|null $id of the record; null when the driver could not tell the id of a new one
     * @param bool $created whether the record was added rather than changed
     */
    public function afterSave(AsCrud $crud, ?string $id, bool $created): string;

    /**
     * Where a page of the record goes once it is deleted (the list stays where it is).
     */
    public function afterDelete(AsCrud $crud, string $id): string;
}
