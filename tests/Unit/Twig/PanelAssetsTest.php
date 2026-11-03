<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Twig;

use Byfareska\Crud\Twig\PanelAssets;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class PanelAssetsTest extends TestCase
{
    public function testTheTagsOfTheToolOfTheApplicationOnly(): void
    {
        // AssetMapper only: Reprise and Encore are not installed, their functions unknown to Twig.
        $twig = new Environment(new ArrayLoader());
        $twig->addFunction(new TwigFunction(
            'importmap',
            static fn (array $entries): string => sprintf(
                '<importmap %s>',
                implode(',', array_filter($entries, is_string(...))),
            ),
            ['is_safe' => ['html']],
        ));

        self::assertSame(
            '<importmap app,panel_bootstrap5>',
            new PanelAssets('importmap')->tags($twig, ['app', 'panel_bootstrap5']),
        );
        self::assertSame('', new PanelAssets('none')->tags($twig, ['app']));
    }
}
