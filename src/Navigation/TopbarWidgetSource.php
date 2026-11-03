<?php

declare(strict_types=1);

namespace Byfareska\Crud\Navigation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What a part of the application adds to the top bar of the panel. The panel collects it; none of them knows the top
 * bar.
 */
#[AutoconfigureTag(self::TAG)]
interface TopbarWidgetSource
{
    public const string TAG = 'byfareska_crud.topbar_widget_source';

    /**
     * @return iterable<TopbarWidget>
     */
    public function widgets(): iterable;
}
