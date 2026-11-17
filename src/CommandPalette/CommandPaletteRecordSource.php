<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * Stored records a part of the application lets the command palette find. They are looked up when something is typed,
 * not indexed.
 */
interface CommandPaletteRecordSource
{
    public const string TAG = 'byfareska_crud.command_palette_record_source';

    /**
     * @return list<PaletteRecord> best matches first; nothing when the text cannot be searched for
     */
    public function search(string $query, int $limit): array;
}
