<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Leaves widgets out for the request being answered - those of the bundle among them: a tenant without the clock, a
 * plan without the notes, a widget behind a feature flag (byfareska_crud.dashboard.disabled_widgets leaves them out for
 * everybody). A service, autoconfigured; every filter has to allow a widget.
 */
#[AutoconfigureTag(self::TAG)]
interface DashboardWidgetFilter
{
    public const string TAG = 'byfareska_crud.dashboard_widget_filter';

    public function allows(DashboardWidget $widget, string $board): bool;
}
