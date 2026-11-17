<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Entity;

/** An enum that cannot translate itself: printed as its value. */
enum Tariff: string
{
    case G11 = 'G11';
    case G12 = 'G12';
}
