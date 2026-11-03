<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * What the palette shows of what was found, and in which order: the entries (pages, commands) its PaletteSearch found
 * and the records the record sources found. The bundle's is {@see BalancedPaletteRanker}; alias this interface to rank
 * otherwise - records first for a search-heavy panel, the most used first, other groups.
 */
interface PaletteRanker
{
    /**
     * @param list<PaletteItem> $entries best first
     * @param list<PaletteItem> $records best first, source by source
     *
     * @return list<PaletteItem> at most $limit
     */
    public function rank(array $entries, array $records, int $limit): array;
}
