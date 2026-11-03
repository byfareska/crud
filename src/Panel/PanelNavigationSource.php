<?php

declare(strict_types=1);

namespace Byfareska\Crud\Panel;

use Byfareska\Crud\Navigation\NavigationItem;
use Byfareska\Crud\Navigation\NavigationSource;

/**
 * The dashboard of every panel that has one, first in its menu.
 */
final readonly class PanelNavigationSource implements NavigationSource
{
    public function __construct(
        private Scopes $scopes,
    ) {
    }

    public function items(): iterable
    {
        foreach ($this->scopes->all() as $scope) {
            if ($scope->dashboard) {
                yield new NavigationItem(
                    section: NavigationItem::SECTION_START,
                    routeName: $scope->dashboardRoute(),
                    label: 'panel.nav.dashboard',
                    icon: 'grid-1x2',
                    role: $scope->role,
                    priority: -1000,
                    scope: $scope->name,
                );
            }
            // More dashboards of the panel, right after its own.
            $priority = -999;
            foreach ($scope->boards as $board => $settings) {
                yield new NavigationItem(
                    section: NavigationItem::SECTION_START,
                    routeName: Scope::boardRoute($board),
                    label: $settings['label'],
                    icon: $settings['icon'],
                    role: $settings['role'],
                    priority: $priority++,
                    scope: $scope->name,
                );
            }
        }
    }
}
