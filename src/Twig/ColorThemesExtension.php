<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The colour themes a theme of the panel offers to pick from (byfareska_crud.color_themes, the themes of daisyUI by
 * default) and which of them are dark (byfareska_crud.dark_color_themes): crud_color_themes() - {names, dark}. The
 * "daisyui" theme draws its picker with them; the stylesheet of the application has to build the same themes.
 */
final class ColorThemesExtension extends AbstractExtension
{
    /**
     * @param list<string> $names
     * @param list<string> $dark
     */
    public function __construct(
        #[Autowire(param: 'byfareska_crud.color_themes')]
        private readonly array $names,
        #[Autowire(param: 'byfareska_crud.dark_color_themes')]
        private readonly array $dark,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('crud_color_themes', $this->colorThemes(...))];
    }

    /**
     * @return array{names: list<string>, dark: list<string>}
     */
    public function colorThemes(): array
    {
        return [
            'names' => $this->names,
            'dark' => array_values(array_intersect($this->dark, $this->names)),
        ];
    }
}
