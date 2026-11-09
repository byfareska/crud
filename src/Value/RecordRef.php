<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use JsonSerializable;
use Stringable;

/**
 * Another record a value points to (a relation): printed by what names it, written in JSON - and so in the addresses,
 * the live props, the API - as its id. A TypedValue of the kind "relation" holds it (blocks type_relation*).
 */
final readonly class RecordRef implements Stringable, JsonSerializable
{
    public function __construct(
        public ?string $id,
        public string $label,
    ) {
    }

    public function __toString(): string
    {
        return $this->label;
    }

    public function jsonSerialize(): ?string
    {
        return $this->id;
    }
}
