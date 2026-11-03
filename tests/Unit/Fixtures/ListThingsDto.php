<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\UseCase\MapsToRequest;

final class ListThingsDto implements MapsToRequest
{
    public function toRequest(): ListThingsDto
    {
        return $this;
    }
}
