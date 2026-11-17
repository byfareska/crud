<?php

declare(strict_types=1);

namespace Byfareska\Crud\Translation;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * The fields of a resource a driver knows besides those AsCrud names (a guessed form has every field of the mapping):
 * each gets a text made of its name until it is translated (CrudDefaultTexts).
 */
interface ResourceFieldNames
{
    public const string TAG = 'byfareska_crud.resource_field_names';

    /**
     * @return list<string>
     */
    public function of(AsCrud $crud): array;
}
