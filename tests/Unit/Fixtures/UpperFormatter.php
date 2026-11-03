<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Value\ValueFormat;
use Byfareska\Crud\Value\ValueFormatter;
use Byfareska\Crud\Value\ValueView;

/** The value in capitals, with where it is printed and the field next to it. */
final readonly class UpperFormatter implements ValueFormatter
{
    public function format(ValueFormat $format, ValueView $value): string
    {
        $text = is_string($value->value) ? $value->value : '';
        $name = is_string($value->item['name'] ?? null) ? $value->item['name'] : '';

        return sprintf('%s (%s, %s)', strtoupper($text), $value->view, $name);
    }
}
