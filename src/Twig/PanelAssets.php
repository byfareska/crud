<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Twig\TwigFunction;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;
use Closure;
use ReflectionFunction;
use Symfony\Bridge\Twig\UndefinedCallableHandler;
use Twig\Environment;
use Twig\Error\SyntaxError;

/**
 * The tags of the entries of a page of the panel (panel_entries()) by the tool the application builds its assets with
 * (byfareska_crud.assets): Vite through Symfony Reprise, Webpack Encore, the importmap of AssetMapper - or none, for a
 * layout that loads them itself. Only the functions of that tool are compiled, so the others need not be installed.
 *
 * "builtin" loads the stylesheet and the script the bundle ships prebuilt (public/, installed by assets:install as
 * bundles/crud/): the panel works with no build of the application's own. "auto" (the default) is "builtin"
 * while the application names no entries of its own, else the tool whose Twig functions are there.
 */
final readonly class PanelAssets
{
    public const string AUTO = 'auto';

    public const string BUILTIN = 'builtin';

    public const string REPRISE = 'reprise';

    public const string ENCORE = 'encore';

    public const string IMPORTMAP = 'importmap';

    public const string NONE = 'none';

    /**
     * What "auto" draws with AssetMapper and StimulusBundle when the application names no entries: the prebuilt
     * stylesheet, the application's importmap (no entry of its own) and the panel's script through it
     * (assets/panel.importmap.js) - the controllers of Symfony UX the application has, in its versions.
     */
    public const string IMPORTMAP_PANEL = 'importmap-panel';

    /** The script of the panel: an entrypoint of the application's importmap (PanelImportMapConfigReader). */
    public const string IMPORTMAP_SCRIPT = '@byfareska/crud/panel.importmap.js';

    /** The prebuilt assets, under the public directory of the bundle. */
    public const string BUILTIN_STYLESHEET = 'bundles/crud/panel/panel.css';

    public const string BUILTIN_SCRIPT = 'bundles/crud/panel/panel.js';

    /** The Twig function of each tool, the one it is told by; and the package it comes with. */
    private const array TOOLS = [
        self::REPRISE => ['reprise_entry_link_tags', 'symfony/reprise'],
        self::ENCORE => ['encore_entry_link_tags', 'symfony/webpack-encore-bundle'],
        self::IMPORTMAP => ['importmap', 'symfony/asset-mapper'],
    ];

    /** Each entry by a function of the tool, tracked (see _assets.html.twig): "%s" is the name of the function. */
    private const string EACH = '{%% for entry in entries %%}'
        . "{{ %s(entry, attributes: {'data-turbo-track': 'reload'}) }}{%% endfor %%}";

    private const string BUILTIN_TAGS = '<link rel="stylesheet" href="{{ css }}" data-turbo-track="reload">'
        . '<script type="module" src="{{ js }}" data-turbo-track="reload"></script>';

    public function __construct(
        #[Autowire(param: 'byfareska_crud.assets')]
        private string $tool = self::AUTO,
        #[Autowire(param: 'byfareska_crud.entries_configured')]
        private bool $entriesConfigured = false,
        #[Autowire(param: 'byfareska_crud.stimulus_importmap')]
        private bool $stimulusImportmap = false,
        private ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * @param list<string> $entries
     */
    #[AsTwigFunction('panel_assets', needsEnvironment: true, isSafe: ['html'])]
    public function tags(Environment $twig, array $entries): string
    {
        $tool = $this->tool($twig);
        if ($tool === self::NONE) {
            return '';
        }
        if ($tool === self::IMPORTMAP_PANEL) {
            return $twig->createTemplate(
                '<link rel="stylesheet" href="{{ css }}" data-turbo-track="reload">'
                . "{{ importmap(js, {'data-turbo-track': 'reload'}) }}",
                'byfareska_crud_importmap_panel_assets',
            )->render([
                'css' => $this->builtinUrl(self::BUILTIN_STYLESHEET, 'panel.css'),
                'js' => self::IMPORTMAP_SCRIPT,
            ]);
        }
        if ($tool === self::BUILTIN) {
            // As assets:install puts them (not through AssetMapper, which maps public/ of the bundle too but would
            // not follow the imports of the chunks), the version of the build in the address.
            return $twig->createTemplate(self::BUILTIN_TAGS, 'byfareska_crud_builtin_assets')->render([
                'css' => $this->builtinUrl(self::BUILTIN_STYLESHEET, 'panel.css'),
                'js' => $this->builtinUrl(self::BUILTIN_SCRIPT, 'panel.js'),
            ]);
        }
        if ($entries === []) {
            return '';
        }
        $template = match ($tool) {
            self::REPRISE => sprintf(self::EACH, 'reprise_entry_link_tags')
                . sprintf(self::EACH, 'reprise_entry_script_tags'),
            self::ENCORE => sprintf(self::EACH, 'encore_entry_link_tags')
                . sprintf(self::EACH, 'encore_entry_script_tags'),
            self::IMPORTMAP => '{{ importmap(entries) }}',
            default => throw new LogicException(sprintf(
                'byfareska_crud.assets is "%s", none of auto, builtin, reprise, encore, importmap, none.',
                $tool,
            )),
        };

        return $twig->createTemplate(
            $template,
            'byfareska_crud_' . $tool . '_assets',
        )->render(['entries' => $entries]);
    }

    /** The tool the pages are drawn with: "auto" decided, a tool named checked to be there. */
    public function tool(Environment $twig): string
    {
        if ($this->tool === self::AUTO) {
            if (!$this->entriesConfigured) {
                // Nothing configured: through the application's AssetMapper when it has one (its Symfony UX), else
                // all prebuilt. Reprise and Encore build only the entries the application gives them.
                return $this->stimulusImportmap && $this->has($twig, 'importmap')
                    ? self::IMPORTMAP_PANEL
                    : self::BUILTIN;
            }
            foreach (self::TOOLS as $tool => [$function]) {
                if ($this->has($twig, $function)) {
                    return $tool;
                }
            }

            throw new LogicException(
                'byfareska_crud.entries names entries of the application, and none of Symfony Reprise, Webpack '
                . 'Encore or AssetMapper is installed to load them: install one, set byfareska_crud.assets, or '
                . 'remove byfareska_crud.entries to use the prebuilt assets of the panel.',
            );
        }
        if (isset(self::TOOLS[$this->tool]) && !$this->has($twig, self::TOOLS[$this->tool][0])) {
            throw new LogicException(sprintf(
                'byfareska_crud.assets is "%s", but its Twig function "%s" is not there: install %s, or set '
                . 'byfareska_crud.assets to the tool the application uses (auto, builtin, reprise, encore, '
                . 'importmap, none).',
                $this->tool,
                self::TOOLS[$this->tool][0],
                self::TOOLS[$this->tool][1],
            ));
        }

        return $this->tool;
    }

    /**
     * Whether Twig has the function for real: not one of the stand-ins of Symfony's UndefinedCallableHandler, which
     * draws nothing for a missing Webpack Encore and throws for a missing AssetMapper ("did you forget to install").
     */
    private function has(Environment $twig, string $name): bool
    {
        try {
            $function = $twig->getFunction($name);
        } catch (SyntaxError) {
            return false;
        }
        $callable = $function?->getCallable();
        if ($callable instanceof Closure) {
            $scope = new ReflectionFunction($callable)->getClosureScopeClass()?->getName();
            if ($scope === UndefinedCallableHandler::class) {
                return false;
            }
        }

        return $function instanceof TwigFunction;
    }

    /** "?v=" and a hash of the file built, so a browser takes a new build at once; nothing for a file not there. */
    private function version(string $file): string
    {
        /** @var array<string, string> $versions */
        static $versions = [];
        $path = dirname(__DIR__, 2) . '/public/panel/' . $file;

        return $versions[$file] ??= is_file($path) ? '?v=' . substr((string) hash_file('xxh128', $path), 0, 12) : '';
    }

    /** A prebuilt file as assets:install puts it, with the version of the build. */
    private function builtinUrl(string $path, string $file): string
    {
        return ($this->requestStack?->getMainRequest()?->getBasePath() ?? '') . '/' . $path . $this->version($file);
    }
}
