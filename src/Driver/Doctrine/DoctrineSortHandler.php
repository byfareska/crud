<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * Orders the query of the list by one kind of {@see DoctrineSort}: a service, found by its class - the one the sort
 * names in DoctrineSort::handler(). Autoconfigured.
 */
interface DoctrineSortHandler
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.doctrine_sort_handler';

    /**
     * Adds the order to those before it (addOrderBy()); an expression of its own is selected as HIDDEN first:
     *
     *     $query->query->addSelect('(SELECT COUNT(o.id) FROM App\Entity\Order o WHERE o.product = e) AS HIDDEN orders')
     *         ->addOrderBy('orders', $descending ? 'DESC' : 'ASC');
     */
    public function apply(DoctrineSort $sort, DoctrineListQuery $query, bool $descending): void;
}
