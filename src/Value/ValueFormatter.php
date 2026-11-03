<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

/**
 * Prints the values of one kind of {@see ValueFormat}: a service, found by its class - the one the format names in
 * ValueFormat::formatter(). Autoconfigured.
 */
interface ValueFormatter
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.value_formatter';

    /**
     * @return string HTML, escaped by the formatter
     */
    public function format(ValueFormat $format, ValueView $value): string;
}
