<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use JsonSerializable;
use Stringable;

/**
 * A value that says what it is, made by a {@see ValueNormalizer} of the application: `new TypedValue('money', '12.50')`
 * is printed by the blocks type_money_list / type_money of the theme, and one with a format by its ValueFormatter
 * (a format of the column comes first). As text, and in JSON, it is its value - what a title, a guard or an action
 * sees of it.
 */
final readonly class TypedValue implements Stringable, JsonSerializable
{
    /**
     * @param string $kind lower-case, snake_case: the "type_<kind>" blocks print it
     * @param mixed $value ready to print: a scalar, a DateTimeImmutable, a list of scalars
     */
    public function __construct(
        public string $kind,
        public mixed $value,
        public ?ValueFormat $format = null,
    ) {
    }

    public function __toString(): string
    {
        return is_scalar($this->value) || $this->value instanceof Stringable ? (string) $this->value : '';
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
