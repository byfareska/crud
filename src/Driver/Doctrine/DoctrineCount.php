<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * How many records the list of a source has, in place of the COUNT of its query - named by the source,
 * `DoctrineSource(countWith: LogCount::class)`: a table of millions, counted by the estimate of PostgreSQL
 * (pg_class.reltuples) while nothing filters it, a count kept in a cache, a query without the joins of the list. A
 * service, autoconfigured.
 *
 *     public function count(DoctrineListQuery $query): array
 *     {
 *         if ($query->filtered()) {
 *             return [count(new Paginator($query->query)), false];
 *         }
 *
 *         return [(int) $this->connection->fetchOne("SELECT reltuples FROM pg_class WHERE relname = 'log'"), false];
 *     }
 */
#[AutoconfigureTag(self::TAG)]
interface DoctrineCount
{
    public const string TAG = 'byfareska_crud.doctrine_count';

    /**
     * @param DoctrineListQuery $query the query of the list, filtered and ordered, before it is paged
     *
     * @return array{int, bool} how many (what the pages go by), and whether there are more than that ("more than N")
     */
    public function count(DoctrineListQuery $query): array;
}
