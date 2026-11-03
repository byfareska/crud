<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Value\TypedValue;
use DateTimeImmutable;
use JsonSerializable;
use Stringable;

/**
 * What a value of any resource is, told from the value itself (or by itself, a TypedValue), and the form in which it
 * is ready to be printed. The kind picks the Twig block that prints it (type_date, type_list, ... in
 * @CrudTheme/crud/values.html.twig).
 */
final readonly class CrudCell
{
    /**
     * @return array{kind: string, value: mixed} the kind is empty, bool, list, date, text or that of a TypedValue
     */
    public static function of(mixed $value): array
    {
        if ($value instanceof TypedValue) {
            return in_array($value->value, [null, '', []], true)
                ? ['kind' => 'empty', 'value' => null]
                : ['kind' => $value->kind, 'value' => $value->value];
        }
        // A Uuid is shown the way it is written in JSON, which is also how the URLs carry it.
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        return match (true) {
            $value === null, $value === '', $value === [] => ['kind' => 'empty', 'value' => null],
            is_bool($value) => ['kind' => 'bool', 'value' => $value],
            // An entry that says what it is (a TypedValue) is printed by the blocks of its kind (crud_list_entry()).
            is_array($value) => ['kind' => 'list', 'value' => array_values(array_filter(
                $value,
                static fn (mixed $entry): bool => is_scalar($entry) || $entry instanceof TypedValue,
            ))],
            // Dates arrive as ISO 8601 text.
            is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value) === 1
                => ['kind' => 'date', 'value' => new DateTimeImmutable($value)],
            is_scalar($value), $value instanceof Stringable => ['kind' => 'text', 'value' => (string) $value],
            default => ['kind' => 'empty', 'value' => null],
        };
    }
}
