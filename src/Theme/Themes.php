<?php

declare(strict_types=1);

namespace Byfareska\Crud\Theme;

use InvalidArgumentException;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The themes of the panel and the one in use - like the form themes of Symfony: a theme is a directory of templates
 * (the pages, the components, the blocks of values and layouts) and names a parent it falls back to, so a theme of the
 * application holds only what it changes. The bundle brings "daisyui" (the default), "bootstrap5" and "tailwind".
 *
 * The theme in use is the one the ThemeSelector chooses - by default that of the panel of the page,
 * byfareska_crud.scopes.<scope>.theme, else byfareska_crud.theme (an env var will do); use() changes it for the rest
 * of a request.
 */
final class Themes implements ResetInterface
{
    public const string BOOTSTRAP5 = 'bootstrap5';
    public const string TAILWIND = 'tailwind';
    public const string DAISYUI = 'daisyui';

    /** The colour themes of daisyUI 5, as daisyui.com lists them: byfareska_crud.color_themes by default. */
    public const array DAISYUI_COLOR_THEMES = [
        'light', 'dark', 'cupcake', 'bumblebee', 'emerald', 'corporate', 'synthwave', 'retro', 'cyberpunk',
        'valentine', 'halloween', 'garden', 'forest', 'aqua', 'lofi', 'pastel', 'fantasy', 'wireframe', 'black',
        'luxury', 'dracula', 'cmyk', 'autumn', 'business', 'acid', 'lemonade', 'night', 'coffee', 'winter', 'dim',
        'nord', 'sunset', 'caramellatte', 'abyss', 'silk',
    ];

    /** Those of them that are dark (color-scheme: dark): byfareska_crud.dark_color_themes by default. */
    public const array DAISYUI_DARK_COLOR_THEMES = [
        'dark', 'synthwave', 'halloween', 'forest', 'aqua', 'black', 'luxury', 'dracula', 'business', 'night',
        'coffee', 'dim', 'sunset', 'abyss',
    ];

    private ?string $current = null;

    /**
     * @param array<string, array{path: string, parent: string|null}> $themes every theme by name, the bundle's too
     */
    public function __construct(
        #[Autowire(param: 'byfareska_crud.themes')]
        private readonly array $themes,
        private readonly ThemeSelector $selector,
    ) {
    }

    public function current(): string
    {
        return $this->current ?? $this->selector->theme();
    }

    /**
     * @throws InvalidArgumentException when there is no such theme
     */
    public function use(string $name): void
    {
        $this->directories($name);
        $this->current = $name;
    }

    public function reset(): void
    {
        $this->current = null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->themes);
    }

    /**
     * Where the templates of a theme are looked for: its own directory first, then those of its parents.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when there is no such theme
     * @throws LogicException when the parents go round in a circle
     */
    public function directories(?string $name = null): array
    {
        $name ??= $this->current();
        $directories = [];
        $seen = [];
        while ($name !== null) {
            $theme = $this->themes[$name]
                ?? throw new InvalidArgumentException(sprintf(
                    'There is no panel theme "%s"; there are "%s".',
                    $name,
                    implode('", "', array_keys($this->themes)),
                ));
            if (isset($seen[$name])) {
                throw new LogicException(sprintf('The panel theme "%s" is its own parent.', $name));
            }
            $seen[$name] = true;
            $directories[] = $theme['path'];
            $name = $theme['parent'];
        }

        return $directories;
    }
}
