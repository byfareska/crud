<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Entity;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity]
#[AsCrud(
    name: 'customer',
    path: '/customers',
    icon: 'people',
    source: new DoctrineSource(Customer::class),
    columns: ['name', 'status', 'channels', 'tariff'],
    details: ['name', 'status', 'channels', 'tariff'],
    title: ['name', 'status'],
    section: 'panel.section.billing',
)]
class Customer implements Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    /**
     * @param list<ContactChannel> $channels
     */
    public function __construct(
        #[ORM\Column]
        public string $name,
        #[ORM\Column]
        public string $number,
        #[ORM\Column(enumType: AccountStatus::class)]
        public AccountStatus $status,
        #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true, enumType: ContactChannel::class)]
        public ?array $channels = null,
        #[ORM\Column(nullable: true, enumType: Tariff::class)]
        public ?Tariff $tariff = null,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->name, $this->number);
    }
}
