<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * How the command palette finds its entries for what was typed - the part to replace for another way of searching
 * (by meaning, with embeddings; a search engine): alias this interface to a service of the application, or name it
 * in byfareska_crud.command_palette.search. The bundle's own is {@see TextPaletteSearch}, letter by letter.
 *
 * It is given what the one typing is offered (already filtered by their roles and their panel) and only picks and
 * orders. Stored records are not its business: they come from the CommandPaletteRecordSources.
 *
 * A search that keeps an index of its own implements {@see IndexedPaletteSearch} as well.
 */
interface PaletteSearch
{
    /**
     * @param string $query what was typed, trimmed; never empty
     * @param array<string, PaletteItem> $items what may be found, by key, in the order the sources declare them
     * @param string $locale the language of the texts of the items
     *
     * @return list<string> the keys of the items that match, best first, at most $limit
     */
    public function search(string $query, array $items, string $locale, int $limit): array;
}
