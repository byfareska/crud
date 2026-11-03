<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * An order, by somebody of a team: relations to filter by under other names, and one further away.
 */
#[ORM\Entity, ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public int $number = 0;

    #[ORM\ManyToOne]
    public ?Person $createdBy = null;

    #[ORM\Embedded(class: Address::class)]
    public Address $address;

    public function __construct()
    {
        $this->address = new Address();
    }
}
