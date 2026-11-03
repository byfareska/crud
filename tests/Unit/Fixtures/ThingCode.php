<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use InvalidArgumentException;

/** A value object of an id made from its text, as a Uuid is. */
final readonly class ThingCode
{
    private function __construct(public string $code)
    {
    }

    public static function fromString(string $code): self
    {
        return preg_match('/^[A-Z]{3}-\d+$/', $code) === 1
            ? new self($code)
            : throw new InvalidArgumentException($code);
    }
}
