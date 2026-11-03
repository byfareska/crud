<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Twig;

use Byfareska\Crud\Theme\Themes;
use Byfareska\Crud\Twig\ColorThemesExtension;
use PHPUnit\Framework\TestCase;

final class ColorThemesExtensionTest extends TestCase
{
    public function testTheThemesOfDaisyUi(): void
    {
        $themes = new ColorThemesExtension(Themes::DAISYUI_COLOR_THEMES, Themes::DAISYUI_DARK_COLOR_THEMES)
            ->colorThemes();

        self::assertCount(35, $themes['names']);
        self::assertSame(['light', 'dark'], array_slice($themes['names'], 0, 2));
        self::assertContains('dracula', $themes['dark']);
        self::assertNotContains('cupcake', $themes['dark']);
    }

    public function testOnlyTheDarkOnesOffered(): void
    {
        $themes = new ColorThemesExtension(['light', 'nord'], ['dark', 'dracula'])->colorThemes();

        self::assertSame(['light', 'nord'], $themes['names']);
        self::assertSame([], $themes['dark']);
    }
}
