<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\UseCase;
use Byfareska\Crud\UseCase\MapsToRequest;

/**
 * Reads a thing: whatever the use case standing in for it (RunUseCase) returns.
 */
#[UseCase(RunUseCase::class)]
final class ReadThingDto implements MapsToRequest
{
    public string $id;

    public function toRequest(): self
    {
        return $this;
    }
}
