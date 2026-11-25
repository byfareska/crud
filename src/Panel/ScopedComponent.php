<?php

declare(strict_types=1);

namespace Byfareska\Crud\Panel;

/**
 * A live component that knows the panel it is in. Its live requests are answered only under the prefix of that panel
 * (LivePanelGuard: in its firewall and access_control), and its address is made there (PanelLiveUrls). Re-rendered by
 * LiveComponent's endpoint (/_components/... - a panel whose routes are not imported), whose address names no panel,
 * it makes its panel the one of the request ({@see ScopeOfComponent}): it is drawn in the theme of that panel. The
 * components of the bundle are; a dashboard widget is by using DashboardWidgetProps.
 */
interface ScopedComponent
{
    /**
     * The name of the panel (route scope), e.g. "admin", or of a board of one; null = it does not know - it is then
     * answered anywhere, so it must not show anything of a panel by itself.
     */
    public function panelScope(): ?string;
}
