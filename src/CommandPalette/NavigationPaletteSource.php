<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

use Byfareska\Crud\Navigation\Navigation;
use Byfareska\Crud\Navigation\NavigationItem;

/**
 * The pages of the menus. What the palette says about a page beyond its name is optional:
 * panel.palette.item.<route>.description and panel.palette.item.<route>.keywords.
 */
final readonly class NavigationPaletteSource implements CommandPaletteSource
{
    public const string GROUP = 'panel.palette.group.navigation';

    public function __construct(
        private Navigation $navigation,
    ) {
    }

    public function entries(): iterable
    {
        foreach ($this->navigation->items() as $item) {
            // The pages of #[AsCrud] resources describe themselves (CrudPaletteSource) - not a link of the
            // application's own to one of them, which is an item like any other.
            // An address of another system is no page of the panel.
            if ($item->resource !== null || $item->url !== null) {
                continue;
            }

            yield new PaletteEntry(
                key: self::keyOf($item),
                group: self::GROUP,
                name: $item->label,
                icon: $item->icon,
                description: sprintf('panel.palette.item.%s.description', $item->routeName),
                keywords: sprintf('panel.palette.item.%s.keywords', $item->routeName),
                routeName: $item->routeName,
                routeParameters: $item->routeParameters,
                role: $item->role,
                scope: $item->scope,
                // As the menu shows it: its parameters, or a text as it is.
                nameParameters: $item->labelParameters,
                translate: $item->translate,
            );
        }
    }

    /**
     * "nav.<route>", and its parameters after "?" when it has some: two items of one route ("open orders", "closed
     * orders") are two entries.
     */
    public static function keyOf(NavigationItem $item): string
    {
        $parameters = $item->routeParameters;
        ksort($parameters);

        return 'nav.' . $item->routeName . ($parameters === [] ? '' : '?' . http_build_query($parameters));
    }
}
