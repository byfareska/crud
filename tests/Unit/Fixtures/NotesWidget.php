<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsDashboardWidget;

#[AsDashboardWidget(
    key: 'test.notes',
    title: 'notes',
    description: 'notes',
    icon: 'sticky',
    priority: 10,
    component: 'Test:Notes',
)]
final class NotesWidget
{
}
