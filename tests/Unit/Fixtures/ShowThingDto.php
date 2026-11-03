<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\UseCase\MapsToRequest;

final class ShowThingDto implements MapsToRequest
{
    public string $id;

    public function toRequest(): ShowThingDto
    {
        return $this;
    }
}
