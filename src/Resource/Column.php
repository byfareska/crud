<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Value\ValueFormat;

final readonly class Column
{
    /**
     * @param string $label a translation key
     * @param string|null $type what the value is when that cannot be told from the value itself ("email"); it only adds
     *     blocks to look for when the value is rendered - see CrudValueRenderer
     * @param ValueFormat|null $format prints the value instead of the blocks of the theme
     */
    public function __construct(
        public string $field,
        public string $label,
        public ?string $type = null,
        public ?ValueFormat $format = null,
    ) {
    }
}
