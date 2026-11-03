<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * What the dashboard gives every widget it renders (DashboardWidget): the id of its place on the dashboard, the
 * dashboard it is on (the name of its panel) - which a widget that keeps settings of its own (a note, the place of
 * the weather) must name, a place being one of that dashboard's layout - and the values of its settings, the
 * defaults filled in.
 *
 * A component using it must not have getPlacement() / getSettings(): the property accessor would read those for the
 * LiveProps. Saying it implements ScopedComponent (panelScope() is here), a live widget is drawn in the theme of its
 * dashboard's panel when it loads after the page.
 */
trait DashboardWidgetProps
{
    #[LiveProp]
    public string $placement = '';

    #[LiveProp]
    public string $board = '';

    /** @var array<string, string|list<string>> */
    #[LiveProp]
    public array $settings = [];

    /** The panel of the dashboard: see ScopedComponent. */
    public function panelScope(): ?string
    {
        return $this->board === '' ? null : $this->board;
    }

    protected function setting(string $name, string $default = ''): string
    {
        $value = $this->settings[$name] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
