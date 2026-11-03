<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsDashboardWidget;
use Byfareska\Crud\Dashboard\WidgetSetting;

#[AsDashboardWidget(key: 'test.sales', title: 'sales', description: 'sales', icon: 'graph-up', priority: 20, settings: [
    new WidgetSetting('period', 'period', choices: ['7' => 'week', '30' => 'month'], default: '7'),
    new WidgetSetting('city', 'city', choicesFrom: 'test.cities'),
])]
final class SalesWidget
{
}
