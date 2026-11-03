<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\UseCase;
use Byfareska\Crud\UseCase\MapsToRequest;
use stdClass;
use ValueError;

#[UseCase(RunUseCase::class)]
final readonly class RunDto implements MapsToRequest
{
    public function __construct(private bool $fails = false)
    {
    }

    public function toRequest(): \stdClass
    {
        if ($this->fails) {
            // As Enum::from() does for a value of no case.
            throw new ValueError('"urgent" is not a valid backing value.');
        }

        return new stdClass();
    }
}
