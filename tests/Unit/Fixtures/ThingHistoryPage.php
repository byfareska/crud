<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrudPage;

#[AsCrudPage(
    resource: 'thing',
    name: 'history',
    component: 'Thing:History',
    icon: 'clock-history',
    role: 'ROLE_AUDITOR',
)]
final class ThingHistoryPage
{
}
