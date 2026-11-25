<?php

declare(strict_types=1);

namespace Byfareska\Crud\Panel;

use Byfareska\Crud\Dashboard\DashboardWidgetProps;

/**
 * The panel a live component is of: the one its {@see ScopedComponent::panelScope()} names - a widget of a dashboard
 * by DashboardWidgetProps, whether or not it says it implements ScopedComponent - made the panel of that board when
 * it names one of the boards of a panel. Its live requests are answered only under the prefix of that panel
 * (LivePanelGuard), the address of it is made there (PanelLiveUrls), and its templates are looked for in the theme of
 * that panel (ScopeOfComponent).
 */
final readonly class ComponentPanel
{
    public function __construct(
        private Scopes $scopes,
    ) {
    }

    /** The name of the panel; null = the component does not know one (it is of none, or of every one). */
    public function of(object $component): ?string
    {
        $name = match (true) {
            $component instanceof ScopedComponent => $component->panelScope(),
            $this->isWidget($component) && method_exists($component, 'panelScope') => $component->panelScope(),
            default => null,
        };

        return is_string($name) && $name !== '' ? $this->scopes->panelOf($name) : null;
    }

    private function isWidget(object $component): bool
    {
        for ($class = $component::class; $class !== false; $class = get_parent_class($class)) {
            if (in_array(DashboardWidgetProps::class, class_uses($class), true)) {
                return true;
            }
        }

        return false;
    }
}
