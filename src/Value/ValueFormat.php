<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Twig\CrudValueRenderer;

/**
 * How the values of a column are printed, given in place of its type: `columns: ['stock' => new ComponentFormat(...)]`
 * of {@see AsCrud}, and the same in "details". It only says what it is: the {@see ValueFormatter} it names (a
 * service) prints the value, so a format of the application's own is two small classes.
 *
 * It comes before the blocks of the theme ({@see CrudValueRenderer}), except for an empty value: that is always
 * the block type_empty.
 */
interface ValueFormat
{
    /**
     * The service that prints the value.
     *
     * @return class-string<ValueFormatter>
     */
    public function formatter(): string;
}
