<?php

declare(strict_types=1);

namespace Byfareska\Crud\Theme;

use Byfareska\Crud\Panel\Scopes;

/**
 * The theme of the panel of the page: byfareska_crud.scopes.<scope>.theme, else byfareska_crud.theme.
 */
final readonly class ScopeThemeSelector implements ThemeSelector
{
    public function __construct(
        private Scopes $scopes,
    ) {
    }

    public function theme(): string
    {
        return $this->scopes->current()->theme;
    }
}
