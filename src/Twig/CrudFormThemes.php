<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

/**
 * The form themes of the forms and filters of the panel: the theme's own, then those of the application
 * (byfareska_crud.form_themes), whose blocks win - `{% form_theme form with crud_form_themes() %}`.
 */
final readonly class CrudFormThemes
{
    public const string THEME = '@CrudTheme/crud/form_layout.html.twig';

    /**
     * @param list<string> $themes
     */
    public function __construct(
        #[Autowire(param: 'byfareska_crud.form_themes')]
        private array $themes = [],
    ) {
    }

    /**
     * @return list<string>
     */
    #[AsTwigFunction('crud_form_themes')]
    public function themes(): array
    {
        return [self::THEME, ...$this->themes];
    }
}
