<?php

declare(strict_types=1);

namespace Byfareska\Crud\Command;

use Byfareska\Crud\CommandPalette\CommandPaletteCatalog;
use Byfareska\Crud\CommandPalette\IndexedPaletteSearch;
use Byfareska\Crud\CommandPalette\PaletteSearch;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hands every entry of the command palette, in every language, to a search that keeps an index of its own
 * (embeddings...). Run it after a deploy - the entries come from the code. The text search needs no index.
 */
#[AsCommand(
    'byfareska:crud:command-palette:index',
    'Indexes the entries of the command palette for the configured search',
)]
final readonly class IndexCommandPaletteCommand
{
    /**
     * @param list<string> $locales
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private PaletteSearch $search,
        private CommandPaletteCatalog $catalog,
        #[Autowire(param: 'byfareska_crud.command_palette.locales')]
        private array $locales,
        #[Autowire(param: 'kernel.enabled_locales')]
        private array $enabledLocales,
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale,
    ) {
    }

    /**
     * @param list<string> $locale only these languages (a deploy of one market), else those configured
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only this language (repeatable); default: command_palette.locales, else the enabled ones')]
        array $locale = [],
    ): int {
        if (!$this->search instanceof IndexedPaletteSearch) {
            $io->note(sprintf('%s keeps no index: nothing to do.', $this->search::class));

            return 0;
        }
        $locales = $locale ?: $this->locales ?: $this->enabledLocales ?: [$this->defaultLocale];
        foreach ($locales as $locale) {
            $count = $this->search->index($this->catalog->items($locale, everything: true), $locale);
            $io->writeln(sprintf('<info>%s</info>: %d entries', $locale, $count));
        }

        return 0;
    }
}
