<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsDashboardWidget;

#[AsDashboardWidget(key: 'test.sales', title: 'again', description: 'again', icon: 'graph-up')]
final class OtherSalesWidget
{
}
