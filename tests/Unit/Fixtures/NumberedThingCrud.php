<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use stdClass;

#[AsCrud(
    name: 'numbered',
    path: '/numbered',
    icon: 'box',
    source: new DoctrineSource(stdClass::class),
    idPattern: '\d+',
)]
final class NumberedThingCrud
{
}
