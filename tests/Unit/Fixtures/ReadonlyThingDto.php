<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\UseCase;
use Byfareska\Crud\UseCase\MapsToRequest;

#[UseCase(ShowThingDto::class)]
final readonly class ReadonlyThingDto implements MapsToRequest
{
    public function __construct(
        public string $id = '',
        public string $note = '',
    ) {
    }

    public function toRequest(): ReadonlyThingDto
    {
        return $this;
    }
}
