<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A line of an order: an id of two fields, one of them a relation.
 */
#[ORM\Entity, ORM\Table(name: 'order_lines')]
class OrderLine
{
    public function __construct(
        #[ORM\Id, ORM\ManyToOne]
        public Order $order,
        #[ORM\Id, ORM\Column]
        public int $number,
        #[ORM\Id, ORM\Column]
        public string $variant = '',
    ) {
    }
}
