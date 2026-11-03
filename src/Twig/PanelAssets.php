<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * The tags of the entries of a page of the panel (panel_entries()) by the tool the application builds its assets with
 * (byfareska_crud.assets): Vite through Symfony Reprise, Webpack Encore, the importmap of AssetMapper - or none, for a
 * layout that loads them itself. Only the functions of that tool are compiled, so the others need not be installed.
 */
final readonly class PanelAssets
{
    public const string REPRISE = 'reprise';

    public const string ENCORE = 'encore';

    public const string IMPORTMAP = 'importmap';

    public const string NONE = 'none';

    /** Each entry by a function of the tool, tracked (see _assets.html.twig): "%s" is the name of the function. */
    private const string EACH = '{%% for entry in entries %%}'
        . "{{ %s(entry, attributes: {'data-turbo-track': 'reload'}) }}{%% endfor %%}";

    public function __construct(
        #[Autowire(param: 'byfareska_crud.assets')]
        private string $tool = self::REPRISE,
    ) {
    }

    /**
     * @param list<string> $entries
     */
    #[AsTwigFunction('panel_assets', needsEnvironment: true, isSafe: ['html'])]
    public function tags(Environment $twig, array $entries): string
    {
        if ($this->tool === self::NONE || $entries === []) {
            return '';
        }
        $template = match ($this->tool) {
            self::REPRISE => sprintf(self::EACH, 'reprise_entry_link_tags')
                . sprintf(self::EACH, 'reprise_entry_script_tags'),
            self::ENCORE => sprintf(self::EACH, 'encore_entry_link_tags')
                . sprintf(self::EACH, 'encore_entry_script_tags'),
            self::IMPORTMAP => '{{ importmap(entries) }}',
            default => throw new LogicException(sprintf(
                'byfareska_crud.assets is "%s", none of reprise, encore, importmap, none.',
                $this->tool,
            )),
        };

        return $twig->createTemplate(
            $template,
            'byfareska_crud_' . $this->tool . '_assets',
        )->render(['entries' => $entries]);
    }
}
