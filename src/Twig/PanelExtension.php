<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\NoPanelAccount;
use Byfareska\Crud\Security\PanelAccount;
use Byfareska\Crud\Theme\Themes;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * What the templates of the panel know of it: its name (panel_name), the panel of the page (panel_scope()) and its
 * Vite entries (panel_entries()), the resource and record the page is of (crud_context(): a CrudContext or null), the
 * theme in use (crud_theme()), and crud_attrs() for attributes given as an array.
 */
final class PanelExtension extends AbstractExtension implements GlobalsInterface
{
    /** The languages written from the right to the left, by their code. */
    private const array RIGHT_TO_LEFT = ['ar', 'arc', 'ckb', 'dv', 'fa', 'he', 'ku', 'ps', 'sd', 'ug', 'ur', 'yi'];

    public function __construct(
        #[Autowire(param: 'byfareska_crud.name')]
        private readonly string $name,
        private readonly Scopes $scopes,
        private readonly Themes $themes,
        private readonly CurrentCrud $currentCrud,
        private readonly PanelAccount $account = new NoPanelAccount(),
        #[Autowire(param: 'byfareska_crud.files.enabled')]
        private readonly bool $files = false,
    ) {
    }

    /**
     * @return array{panel_name: string, crud_files_enabled: bool}
     */
    public function getGlobals(): array
    {
        // Whether byfareska_crud.files is enabled: the layout has the window of the library then.
        return ['panel_name' => $this->name, 'crud_files_enabled' => $this->files];
    }

    public function getFilters(): array
    {
        return [
            // A moment as the clock of the panel shows it, for a translation that formats it (WallClockRuntime).
            new TwigFilter('crud_wall_clock', [WallClockRuntime::class, 'of']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('panel_scope', $this->scopes->current(...)),
            // The direction of the text of a language: "rtl" for Arabic, Persian, Urdu, Hebrew...
            new TwigFunction('panel_dir', self::directionOf(...)),
            // The name the pages of the panel go by: its "brand", else byfareska_crud.name.
            new TwigFunction('panel_brand', fn (): string => $this->scopes->current()->brand ?? $this->name),
            // A function, not a global: the panel and the theme are known only once the request is.
            new TwigFunction('panel_entries', $this->entries(...)),
            new TwigFunction('crud_context', $this->currentCrud->get(...)),
            // The name of the theme in use: "daisyui", "bootstrap5", "tailwind" or one of the application's.
            new TwigFunction('crud_theme', $this->themes->current(...)),
            new TwigFunction('crud_attrs', $this->attributes(...), ['is_safe' => ['html']]),
            // The one signed in, as the menu shows them (PanelAccount) - also without Symfony Security.
            new TwigFunction('panel_account', fn (): PanelAccount => $this->account),
            // An icon, as the theme draws it: @CrudTheme/crud/icon.html.twig (Bootstrap Icons by default).
            new TwigFunction('crud_icon', $this->icon(...), ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }

    /**
     * The Vite entries of the page: those of its panel, "{theme}" being the theme it is drawn with.
     *
     * @return list<string>
     */
    public function entries(): array
    {
        $theme = $this->themes->current();
        $entries = array_map(
            static fn (string $entry): string => str_replace(Scope::THEME, $theme, $entry),
            $this->scopes->current()->entries,
        );

        return array_values(array_unique($entries));
    }

    /**
     * "rtl" for a language written from the right (by its code, "ar" or "fa_IR"), else "ltr".
     */
    public static function directionOf(string $locale): string
    {
        $language = strtolower(strtok($locale, '_-') ?: $locale);

        return in_array($language, self::RIGHT_TO_LEFT, true) ? 'rtl' : 'ltr';
    }

    /**
     * An icon, drawn by the template of the theme: a theme of another set of icons (an SVG sprite, Material) changes
     * that one file.
     *
     * @param string $name the name of the icon, as the bundle names them (Bootstrap Icons: "plus-lg", "trash")
     * @param string $class more classes (a margin, a size)
     * @param array<string, string|int|bool|null> $attributes more attributes (a style)
     */
    public function icon(Environment $twig, string $name, string $class = '', array $attributes = []): string
    {
        // Inline, as the tag it stands for: no white space of the template around it.
        return trim($twig->render(
            '@CrudTheme/crud/icon.html.twig',
            ['name' => $name, 'class' => $class, 'attributes' => $attributes],
        ));
    }

    /**
     * HTML attributes from an array, escaped: true = the attribute alone, false and null = left out.
     *
     * @param array<string, string|int|bool|null> $attributes
     */
    public function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $name = htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE);
            $html .= $value === true ? ' ' . $name : sprintf(
                ' %s="%s"',
                $name,
                htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE),
            );
        }

        return $html;
    }
}
