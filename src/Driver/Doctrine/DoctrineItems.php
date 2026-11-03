<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Adds to the items of a list of entities - and to a record read by its id - what no field of the entity has: the
 * orders of a customer, the stock of a product kept by another system. Given a whole page at once, so it asks for all
 * of them by one query, not one for each row:
 *
 *     final readonly class OrderCounts implements DoctrineItems
 *     {
 *         public function resource(): string { return 'customer'; }
 *
 *         public function extend(AsCrud $crud, array $entities, array $items): array
 *         {
 *             $counts = $this->orders->countByCustomer(array_column($items, 'id'));
 *             foreach ($items as $index => $item) {
 *                 $items[$index]['orders'] = $counts[$item['id']] ?? 0;
 *             }
 *
 *             return $items;
 *         }
 *     }
 *
 * A column computed by the database itself is a select of a DoctrineListExtension instead
 * (`$query->query->addSelect('(SELECT COUNT(o.id) FROM App\Entity\Order o WHERE o.customer = e) AS orders')`): what
 * a query selects besides the entity is in its item under its alias.
 */
#[AutoconfigureTag(self::TAG)]
interface DoctrineItems
{
    public const string TAG = 'byfareska_crud.doctrine_items';

    /** The resource whose items it adds to; EVERY for all. */
    public const string EVERY = '*';

    public function resource(): string;

    /**
     * @param list<object> $entities of the items, in their order
     * @param list<array<string, mixed>> $items by field, as the list shows them (values made printable)
     *
     * @return list<array<string, mixed>> the items with what it adds, in the same order
     */
    public function extend(AsCrud $crud, array $entities, array $items): array;
}
