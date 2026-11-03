<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * An order of the list of a {@see DoctrineSource} of the application's own, given in "sortable" in place of a field:
 * `sortable: ['orders' => new OrderCount()]` - by an expression, a subquery, a join of another entity. It only says
 * what it is: the {@see DoctrineSortHandler} it names (a service) adds it to the query. The name is that of the column
 * whose heading orders by it, and of the order in "sort" (`sort: ['-orders']`).
 */
interface DoctrineSort
{
    /**
     * The service that orders the query by it.
     *
     * @return class-string<DoctrineSortHandler>
     */
    public function handler(): string;
}
