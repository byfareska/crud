<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsTopbarWidget;

#[AsTopbarWidget(priority: 5)]
final class BellWidget
{
}
