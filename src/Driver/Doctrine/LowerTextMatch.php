<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * A text in any case: LOWER() of the column, the typed text in lower case.
 */
final readonly class LowerTextMatch implements DoctrineTextMatch
{
    public function column(string $path): string
    {
        return sprintf('LOWER(%s)', $path);
    }

    public function text(string $typed): string
    {
        return mb_strtolower($typed);
    }
}
