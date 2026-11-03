<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\UseCase\MapsToRequest;

/** A DTO shared with an API: an id of a number, given to its constructor. */
final readonly class NumberedThingDto implements MapsToRequest
{
    public function __construct(public int $id)
    {
    }

    public function toRequest(): self
    {
        return $this;
    }
}
