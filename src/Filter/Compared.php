<?php

declare(strict_types=1);

namespace Byfareska\Crud\Filter;

/**
 * What a filter of {@see ComparisonType} asks for: a field compared with a value, or between two.
 */
final readonly class Compared
{
    /**
     * @param mixed $value what was typed, as its field makes it (a number, a date, an entity); for Between where the
     *     range starts - null for no start
     * @param mixed $to for Between where the range ends - null for no end
     */
    public function __construct(
        public ComparisonKind $comparison,
        public mixed $value,
        public mixed $to = null,
    ) {
    }

    /**
     * Whether a value of a filter asks for nothing: no value, an empty text or an empty list.
     */
    public static function isEmpty(mixed $value): bool
    {
        return in_array($value, [null, '', []], true) || (is_countable($value) && count($value) === 0);
    }
}
