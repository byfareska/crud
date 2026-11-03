<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * A {@see PaletteSearch} that has to see every entry beforehand - embeddings of their texts, a search engine's index.
 * `bin/console byfareska:crud:command-palette:index` hands it every entry (whoever may use it) in every language of the
 * application; run it after a deploy that changed the entries or their texts.
 */
interface IndexedPaletteSearch extends PaletteSearch
{
    /**
     * @param array<string, PaletteItem> $items every entry, by key, in one language
     *
     * @return int how many it (re)indexed
     */
    public function index(array $items, string $locale): int;
}
