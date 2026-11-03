<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Navigation;

use Byfareska\Crud\Navigation\TopbarWidget;
use Byfareska\Crud\Navigation\TopbarWidgetSource;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Panel\Scopes;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Stringable;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The bar at the top of the panel: the field that opens the command palette, the way to the other panels, the theme,
 * what the TopbarWidgetSources add and the user menu.
 */
#[AsTwigComponent('Panel:Topbar', template: 'components/Panel/Topbar.html.twig')]
final class Topbar
{
    /** The button that opens the menu on a narrow screen. */
    public bool $sidebar = true;

    /** The field that opens the command palette. */
    public bool $palette = true;

    /** More entries of the user's menu, above "sign out": the layout's block "user_menu_items", drawn (HTML). */
    public string|Stringable $userMenuItems = '';

    /**
     * @param iterable<TopbarWidgetSource> $sources
     */
    public function __construct(
        #[AutowireIterator(TopbarWidgetSource::TAG)]
        private readonly iterable $sources,
        private readonly Scopes $scopes,
    ) {
    }

    /**
     * What is shown next to the user menu, as names of Twig components.
     *
     * @return list<string>
     */
    public function getWidgets(): array
    {
        return array_map(static fn (TopbarWidget $widget): string => $widget->component, $this->getShown());
    }

    /**
     * What is shown next to the user menu in the panel of the page, for the one looking: by priority, with its props.
     *
     * @return list<TopbarWidget>
     */
    public function getShown(): array
    {
        $scope = $this->scopes->current()->name;
        $widgets = [];
        foreach ($this->sources as $source) {
            foreach ($source->widgets() as $widget) {
                if (
                    ($widget->scope === null || $widget->scope === $scope)
                    && $this->scopes->isGrantedIn(null, $widget->role)
                ) {
                    $widgets[] = $widget;
                }
            }
        }

        usort($widgets, static fn (TopbarWidget $a, TopbarWidget $b): int => $a->priority <=> $b->priority);

        return $widgets;
    }

    /**
     * The other panels the one looking may go to.
     *
     * @return list<Scope>
     */
    public function getPanels(): array
    {
        return $this->scopes->others();
    }
}
