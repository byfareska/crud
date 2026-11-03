<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use JsonSerializable;
use Stringable;

/**
 * An id as it is written in JSON (a Uuid included), else as a scalar or a Stringable turns into text.
 */
final readonly class DefaultIdMapper implements IdMapper
{
    public function toId(mixed $value): ?string
    {
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        if (is_scalar($value) || $value instanceof Stringable) {
            $id = (string) $value;

            return $id === '' ? null : $id;
        }

        return null;
    }
}
