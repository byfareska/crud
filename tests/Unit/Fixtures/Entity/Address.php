<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A value object kept in the columns of its entity.
 */
#[ORM\Embeddable]
class Address
{
    public function __construct(
        #[ORM\Column]
        public string $city = '',
    ) {
    }
}
