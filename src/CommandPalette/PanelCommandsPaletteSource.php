<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

use Byfareska\Crud\Panel\Scopes;

/**
 * Commands of the panel itself, run in the browser: the theme, the dashboard of each panel, the way to the others.
 */
final readonly class PanelCommandsPaletteSource implements CommandPaletteSource
{
    public const string GROUP = 'panel.palette.group.commands';

    public function __construct(
        private Scopes $scopes,
    ) {
    }

    public function entries(): iterable
    {
        yield new PaletteEntry(
            key: 'command.theme.toggle',
            group: self::GROUP,
            name: 'panel.palette.item.theme.name',
            icon: 'circle-half',
            description: 'panel.palette.item.theme.description',
            keywords: 'panel.palette.item.theme.keywords',
            clientAction: 'theme:toggle',
            command: true,
        );

        foreach ($this->scopes->all() as $scope) {
            // The dashboard of each panel: the page is opened, then its grid does it (dashboard-grid controller).
            if ($scope->dashboard) {
                foreach (
                    [
                    'edit' => 'grid-1x2',
                    'add' => 'plus-square',
                    'reset' => 'arrow-counterclockwise',
                    ] as $command => $icon
                ) {
                    yield new PaletteEntry(
                        key: 'command.dashboard.' . $scope->name . '.' . $command,
                        group: self::GROUP,
                        name: 'panel.palette.item.dashboard_' . $command . '.name',
                        icon: $icon,
                        description: 'panel.palette.item.dashboard_' . $command . '.description',
                        keywords: 'panel.palette.item.dashboard_' . $command . '.keywords',
                        routeName: $scope->dashboardRoute(),
                        clientAction: 'dashboard:' . $command,
                        command: true,
                        role: $scope->role,
                        scope: $scope->name,
                    );
                }
            }

            // The way into this panel, from every other one.
            $home = $scope->homeRoute();
            if ($home !== null) {
                foreach ($this->scopes->all() as $from) {
                    if ($from->name !== $scope->name) {
                        yield new PaletteEntry(
                            key: 'command.panel.' . $scope->name . '.from.' . $from->name,
                            group: NavigationPaletteSource::GROUP,
                            name: $scope->label,
                            icon: $scope->icon,
                            description: 'panel.palette.item.panel_switch.description',
                            routeName: $home,
                            role: $scope->role,
                            scope: $from->name,
                        );
                    }
                }
            }
        }
    }
}
