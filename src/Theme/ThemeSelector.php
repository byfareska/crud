<?php

declare(strict_types=1);

namespace Byfareska\Crud\Theme;

/**
 * Which theme a page is drawn with, when nothing chose one for the request ({@see Themes::use()}). The default is
 * {@see ScopeThemeSelector}: the theme of the panel of the page. An application chooses otherwise - a theme the user
 * picked - with a service of its own aliased to this interface.
 */
interface ThemeSelector
{
    /** The name of a theme: "daisyui", "bootstrap5", "tailwind" or one of byfareska_crud.themes. */
    public function theme(): string;
}
