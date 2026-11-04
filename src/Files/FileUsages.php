<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Where each file is used, whatever keeps it in the record (a column, JSON, a relation): the index the panel writes
 * when a record is saved or deleted (FileUsageListener), so a file is not deleted from a library while a record shows
 * it, and the files nobody uses can be pruned (byfareska:crud:files:prune).
 *
 * The index knows what the panel saved: records changed otherwise (an import, an API) are indexed again by
 * byfareska:crud:files:reindex, or by the application calling replace() itself. The bundle's are
 * {@see Doctrine\DoctrineFileUsages} (a table) and {@see NoFileUsages} (no index: nothing is pruned).
 */
interface FileUsages
{
    /** Whether it keeps an index at all: without one nothing is pruned, and no file is known to be unused. */
    public function tracks(): bool;

    /**
     * The files a field of a record uses now, in place of those it used.
     *
     * @param list<FileReference> $references
     */
    public function replace(string $resource, string $id, string $field, array $references): void;

    /** A record deleted: it uses no file any more. */
    public function forget(string $resource, string $id): void;

    /**
     * @return list<FileUsage>
     */
    public function of(FileReference $reference): array;

    /**
     * Those of the files that are used somewhere.
     *
     * @param list<FileReference> $references
     * @return list<FileReference>
     */
    public function used(array $references): array;
}
