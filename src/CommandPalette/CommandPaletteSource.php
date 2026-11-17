<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * What a part of the application puts into the command palette. Every implementation is collected,
 * so a part of the application adds entries without the palette knowing about it.
 */
interface CommandPaletteSource
{
    public const string TAG = 'byfareska_crud.command_palette_source';

    /**
     * @return iterable<PaletteEntry>
     */
    public function entries(): iterable;
}
