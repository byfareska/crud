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
 * LiveProps. A live widget using it is of the panel of its dashboard (ComponentPanel): its live requests are answered
 * only under the prefix of that panel, and it is drawn in the theme of that panel when it loads after the page; saying
 * it implements ScopedComponent (panelScope() is here) is the same.
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
