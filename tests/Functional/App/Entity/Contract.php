<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Entity;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[AsCrud(
    name: 'contract',
    path: '/contracts',
    icon: 'file-text',
    source: new DoctrineSource(Contract::class, filters: ['customer']),
    columns: ['number', 'customer'],
    details: ['number', 'customer'],
    title: ['number'],
    filtersInView: ['customer'],
)]
class Contract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $number,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        public Customer $customer,
    ) {
    }
}
