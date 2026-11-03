<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * How a text of a filter is compared with a text column ("contains", "starts", =...): what both sides are made
 * before - by default in any case, LOWER() of the column and the typed text in lower case. Alias it for what the
 * database does better: a column of a case-insensitive collation as it is (its index is used), unaccent() of
 * PostgreSQL (a DQL function of your own) so "zolc" finds "żółć".
 */
interface DoctrineTextMatch
{
    /** The column in DQL, as it is compared: 'LOWER(%s)' of it, given its path ("e.name"). */
    public function column(string $path): string;

    /** The typed text, as it is compared. */
    public function text(string $typed): string;
}
