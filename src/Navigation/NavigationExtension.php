<?php

declare(strict_types=1);

namespace Byfareska\Crud\Navigation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Changes the menu for the request, after every NavigationSource gave its items: hides one (a module switched off
 * for a tenant), moves one (the order a user chose), gives one a badge, renames a section. A service, autoconfigured;
 * by the priority of its tag. Who may see an item is still asked after it (its role, its panel's).
 *
 *     public function extend(array $items): array
 *     {
 *         return array_map(fn (NavigationItem $item): NavigationItem => $item->resource === 'order'
 *             ? $item->with(badge: fn (): int => $this->orders->countToShip())
 *             : $item, $items);
 *     }
 */
#[AutoconfigureTag(self::TAG)]
interface NavigationExtension
{
    public const string TAG = 'byfareska_crud.navigation_extension';

    /**
     * @param list<NavigationItem> $items every item of every panel, by priority
     *
     * @return list<NavigationItem> what the menus are to have, in order (sorted by priority again after it)
     */
    public function extend(array $items): array;
}
