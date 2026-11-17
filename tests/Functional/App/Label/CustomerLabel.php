<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App\Label;

use Byfareska\Crud\Autocomplete\RelationChoiceLabel;
use Byfareska\Crud\Tests\Functional\App\Entity\Customer;

/** Registered by autoconfiguration alone, as an application does: no tag by hand. */
final readonly class CustomerLabel implements RelationChoiceLabel
{
    public function supports(object $entity): bool
    {
        return $entity instanceof Customer;
    }

    public function label(object $entity): string
    {
        assert($entity instanceof Customer);

        return sprintf('%s · label', $entity->number);
    }
}
