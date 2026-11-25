<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity]
class Person implements Stringable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    /** What not everybody who sees the person is to know. */
    #[ORM\Column]
    public string $phone = '';

    public function __construct(
        #[ORM\Column]
        public string $name,
        #[ORM\ManyToOne]
        public ?Team $team = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
