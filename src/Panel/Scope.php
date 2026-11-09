<?php

declare(strict_types=1);

namespace Byfareska\Crud\Panel;

/**
 * A panel of the application - a route scope: the pages imported by one "byfareska_crud" route import, with a menu,
 * a command palette, a dashboard and a layout of their own (byfareska_crud.scopes.<name>).
 */
final readonly class Scope
{
    /** The route attribute (a default of every route of the scope) saying which panel a page is in. */
    public const string ATTRIBUTE = '_scope';

    /** What stands for the theme a page is drawn with in the name of an entry, e.g. "panel_{theme}". */
    public const string THEME = '{theme}';

    /**
     * @param string $name e.g. "admin"
     * @param string $label translation key, what the panel switch calls it
     * @param string $icon Bootstrap Icons name
     * @param string $layout the template the pages of the scope extend
     * @param string $theme the theme its pages are drawn with ("bootstrap5", "tailwind" or one of the application's)
     * @param list<string> $entries the Vite entries its pages load: byfareska_crud.entries, then its own; THEME in a
     *     name stands for the theme of the page
     * @param string|null $role who may go into it; null = everybody. The bundle asks it of the panel switch, the
     *     dashboard, the menu and the palette, and of every way to the resources of the panel (Scopes::isGrantedIn()):
     *     a resource without a role of its own is still only for those the panel is for. access_control may guard the
     *     addresses as well (`attributes: { _scope: admin }`), but sees neither /_components/... nor the pages of the
     *     application's with #[MapCrud]
     * @param bool $dashboard whether "/" of the scope is a dashboard of widgets
     * @param string|null $home the route the panel switch leads to; null = its dashboard
     * @param string|null $brand the name its pages go by (the menu, the title of the window); null =
     *     byfareska_crud.name - a white-label panel of a shop with the shop's name
     * @param array<string, array{
     *     label: string,
     *     icon: string,
     *     path: string,
     *     role: string|null,
     *     template: string|null,
     * }> $boards more dashboards of the panel, by name
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $icon,
        public string $layout,
        public string $theme,
        public array $entries,
        public ?string $role,
        public bool $dashboard,
        public ?string $home,
        public ?string $brand = null,
        public array $boards = [],
    ) {
    }

    /**
     * The route of a dashboard - of the panel's own ($board its name) or of one of its boards.
     */
    public static function boardRoute(string $board, string $action = ''): string
    {
        return 'crud_dashboard_' . $board . ($action === '' ? '' : '_' . $action);
    }

    /** Whether the board is a dashboard of this panel: its own, or one of "boards". */
    public function hasBoard(string $board): bool
    {
        return ($board === $this->name && $this->dashboard) || isset($this->boards[$board]);
    }

    /**
     * The same panel with some of its arguments changed, for the request (ScopeExtension): not its name nor whether
     * it has a dashboard - its routes are made of them.
     */
    public function with(mixed ...$changes): self
    {
        $fixed = array_intersect(array_keys($changes), ['name', 'dashboard', 'boards']);
        if ($fixed !== [] || array_is_list($changes) && $changes !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Panel "%s": only named arguments change, and not its name nor its dashboard.',
                $this->name,
            ));
        }

        // @phpstan-ignore argument.type (named arguments of the constructor)
        return new self(...[
            'name' => $this->name,
            'label' => $this->label,
            'icon' => $this->icon,
            'layout' => $this->layout,
            'theme' => $this->theme,
            'entries' => $this->entries,
            'role' => $this->role,
            'dashboard' => $this->dashboard,
            'home' => $this->home,
            'brand' => $this->brand,
            'boards' => $this->boards,
            ...$changes,
        ]);
    }

    /**
     * The route of the live components drawn on the pages of the scope: under its prefix (/admin/_crud/...), so a
     * live request is in the firewall of its panel - a partner portal on a firewall of its own, a user class of its
     * own (PanelLiveUrls).
     */
    public function liveRoute(): string
    {
        return 'crud_live_' . $this->name;
    }

    /** The route of the dashboard of the scope. */
    public function dashboardRoute(string $action = ''): string
    {
        return self::boardRoute($this->name, $action);
    }

    /**
     * Where the panel switch and the brand lead to: "home", else its dashboard, else "/" of the panel, which leads to
     * its first list (CrudRouteLoader).
     */
    public function homeRoute(): string
    {
        return $this->home ?? ($this->dashboard ? $this->dashboardRoute() : self::firstListRoute($this->name));
    }

    /**
     * The autocomplete endpoint of the panel (symfony/ux-autocomplete), under its prefix: its firewall and
     * access_control cover the searches of the relations of its forms and filters as they cover its pages.
     */
    public function autocompleteRoute(): string
    {
        return 'crud_autocomplete_' . $this->name;
    }

    /** The route of "/" of a panel without a dashboard: a redirect to its first list. */
    public static function firstListRoute(string $scope): string
    {
        return 'crud_home_' . $scope;
    }
}
