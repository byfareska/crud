<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrud;

#[AsCrud(name: 'thing', path: '/things', icon: 'box', list: ListThingsDto::class, read: ShowThingDto::class)]
final class ThingCrud
{
}
