<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use BackedEnum;
use DateTimeInterface;
use JsonSerializable;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use UnitEnum;

/**
 * The values of PHP: a scalar or null as it is, an enum as its value (or name), a date as ISO 8601 text, a Stringable
 * as its text, a JsonSerializable as its JSON (a Uuid as it is written there, which is also how the URLs carry it),
 * a list as a list of those.
 */
#[AsTaggedItem(priority: -100)]
final readonly class PlainValueNormalizer implements ValueNormalizer
{
    public function supports(mixed $value): bool
    {
        return $value === null
            || is_scalar($value)
            || $value instanceof UnitEnum
            || $value instanceof DateTimeInterface
            || $value instanceof Stringable
            || $value instanceof JsonSerializable
            || is_iterable($value);
    }

    public function normalize(mixed $value, CrudValues $values): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof Stringable => (string) $value,
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            is_iterable($value) => array_values(array_map(
                $values->normalize(...),
                is_array($value) ? $value : iterator_to_array($value, false),
            )),
            default => $value,
        };
    }
}
