<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Theme;

use Byfareska\Crud\Theme\ThemeSelector;

/** A selector whose answer the test changes, as the panel of the page would. */
final class SettableThemeSelector implements ThemeSelector
{
    public function __construct(
        public string $theme,
    ) {
    }

    public function theme(): string
    {
        return $this->theme;
    }
}
