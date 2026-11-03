<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The widgets a part of the application offers for the dashboards. Every implementation is collected, so a widget is
 * added without the dashboard knowing about it.
 */
#[AutoconfigureTag(self::TAG)]
interface DashboardWidgetSource
{
    public const string TAG = 'byfareska_crud.dashboard_widget_source';

    /**
     * @return iterable<DashboardWidget>
     */
    public function widgets(): iterable;
}
