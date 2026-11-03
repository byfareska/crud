<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Twig;

use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Theme\ScopeThemeSelector;
use Byfareska\Crud\Theme\Themes;
use Byfareska\Crud\Twig\PanelExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Vite entries a page loads (panel_entries()): those of every panel, then its own, "{theme}" being the theme the
 * page is drawn with.
 */
final class PanelEntriesTest extends TestCase
{
    private Themes $themes;

    public function testTheEntriesOfEveryPanelThenItsOwnForItsTheme(): void
    {
        $extension = $this->extension('panel');

        self::assertSame(['panel_bootstrap5', 'app'], $extension->entries());
    }

    public function testAPanelOfAnotherThemeAndEntriesOfItsOwn(): void
    {
        $extension = $this->extension('docs');

        self::assertSame(['panel_tailwind', 'app', 'docs_tailwind', 'search'], $extension->entries());
    }

    public function testTheThemeTheRequestChoseIsTheOneOfTheEntries(): void
    {
        $extension = $this->extension('docs');
        $this->themes->use('bootstrap5');

        self::assertSame(['panel_bootstrap5', 'app', 'docs_bootstrap5', 'search'], $extension->entries());
    }

    private function extension(string $scope): PanelExtension
    {
        $requests = new RequestStack();
        $requests->push(new Request(attributes: [Scope::ATTRIBUTE => $scope]));
        $config = [
            'panel' => $this->scope(null, []),
            // "app" is one of every panel already: loaded once.
            'docs' => $this->scope('tailwind', ['docs_{theme}', 'app', 'search']),
        ];
        $current = new CurrentCrud($requests);
        $scopes = new Scopes(
            $config,
            'layout.html.twig',
            'bootstrap5',
            ['panel_{theme}', 'app'],
            $requests,
            $this->actor(),
            $current,
        );
        $this->themes = new Themes(
            [
                'bootstrap5' => ['path' => '/bootstrap5', 'parent' => null],
                'tailwind' => ['path' => '/tailwind', 'parent' => null],
            ],
            new ScopeThemeSelector($scopes),
        );

        return new PanelExtension('Panel', $scopes, $this->themes, $current);
    }

    /**
     * @param list<string> $entries
     *
     * @return array{
     *     label: null,
     *     icon: string,
     *     layout: null,
     *     theme: string|null,
     *     entries: list<string>,
     *     role: null,
     *     dashboard: bool,
     *     home: null,
     * }
     */
    private function scope(?string $theme, array $entries): array
    {
        return [
            'label' => null,
            'icon' => 'window',
            'layout' => null,
            'theme' => $theme,
            'entries' => $entries,
            'role' => null,
            'dashboard' => true,
            'home' => null,
        ];
    }

    private function actor(): Actor
    {
        return new readonly class implements Actor {
            public function id(): ?string
            {
                return null;
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return true;
            }
        };
    }
}
