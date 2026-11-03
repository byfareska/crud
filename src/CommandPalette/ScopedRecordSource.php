<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * A {@see CommandPaletteRecordSource} that looks only where the palette is: told the panel, it leaves the records of
 * the others unsearched - one query per resource of that panel, not of every panel, for every key typed.
 */
interface ScopedRecordSource extends CommandPaletteRecordSource
{
    /**
     * @param string|null $scope the panel the palette is in; null = every panel
     *
     * @return list<PaletteRecord> best matches first
     */
    public function searchIn(string $query, int $limit, ?string $scope): array;
}
