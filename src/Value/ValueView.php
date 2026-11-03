<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\Column;

/**
 * One value of a record where a page prints it: what a {@see ValueFormatter} gets, and what the blocks of the theme
 * get as variables of the same names.
 */
final readonly class ValueView
{
    /**
     * @param mixed $value ready to print: a date is a DateTimeImmutable, a list holds scalars
     * @param mixed $raw as the use case returned it
     * @param array<string, mixed> $item the whole record
     * @param 'list'|'detail' $view the table of the list, or the preview drawer and the details page
     */
    public function __construct(
        public AsCrud $crud,
        public Column $column,
        public mixed $value,
        public mixed $raw,
        public array $item,
        public string $view,
    ) {
    }
}
