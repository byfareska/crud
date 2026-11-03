<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsDashboardWidget;

/**
 * One component, two widgets: a counter of new orders and one of open tickets.
 */
#[AsDashboardWidget(
    key: 'test.orders',
    title: 'orders',
    description: 'orders',
    icon: 'cart',
    props: ['counts' => 'orders'],
)]
#[AsDashboardWidget(
    key: 'test.tickets',
    title: 'tickets',
    description: 'tickets',
    icon: 'chat',
    props: ['counts' => 'tickets'],
)]
final class CounterWidget
{
}
