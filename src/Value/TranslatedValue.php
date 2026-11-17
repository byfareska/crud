<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use JsonSerializable;
use Stringable;

/**
 * A value that says how it is named in the language of the request - a case of an enum that is a
 * TranslatableInterface: printed as its translation, written in JSON (live props, addresses, the API) as its value.
 * A TypedValue of the kind "enum" holds it (blocks type_enum*).
 */
final readonly class TranslatedValue implements Stringable, JsonSerializable
{
    public function __construct(
        public int|string $value,
        public string $label,
    ) {
    }

    public function __toString(): string
    {
        return $this->label;
    }

    public function jsonSerialize(): int|string
    {
        return $this->value;
    }
}
