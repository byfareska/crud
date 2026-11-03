<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * The bundle's {@see PaletteRanker}: the entries first, then the records - the entries keep at least half of the
 * places when there are that many, so records of three resources do not push the page "Users" out of view; what one
 * side leaves free the other takes.
 */
final readonly class BalancedPaletteRanker implements PaletteRanker
{
    public function rank(array $entries, array $records, int $limit): array
    {
        $kept = max($limit - count($records), min(count($entries), (int) ceil($limit / 2)));
        $entries = array_slice($entries, 0, $kept);

        return [...$entries, ...array_slice($records, 0, max(0, $limit - count($entries)))];
    }
}
