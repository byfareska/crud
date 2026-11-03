<?php

declare(strict_types=1);

namespace Byfareska\Crud\Navigation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The pages a part of the application adds to the menu of the panel. The panel collects them; none of them knows the
 * menu.
 */
#[AutoconfigureTag(self::TAG)]
interface NavigationSource
{
    public const string TAG = 'byfareska_crud.navigation_source';

    /**
     * @return iterable<NavigationItem>
     */
    public function items(): iterable;
}
