<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity]
class Team implements Stringable
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
