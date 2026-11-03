<?php

declare(strict_types=1);

namespace Byfareska\Crud\Panel;

/**
 * A live component that knows the panel it is in. Re-rendered by the live endpoint (/_components/...), whose address
 * names no panel, it makes its panel the one of the request ({@see ScopeOfComponent}): it is drawn in the theme of
 * that panel. The components of the bundle are; a dashboard widget is by using DashboardWidgetProps and saying it
 * implements this.
 */
interface ScopedComponent
{
    /** The name of the panel (route scope), e.g. "admin"; null = it does not know. */
    public function panelScope(): ?string;
}
