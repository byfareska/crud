<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * Makes the new entity the add form of a resource starts with. The bundle's is {@see ConstructorEntityFactory}: its
 * constructor without arguments. Alias the interface (or decorate it) for an entity made another way - a named
 * constructor (`Order::draft($this->clock->now())`), a factory of the domain, an owner given at once - one service for
 * every resource; it decides by $crud or $source which it makes.
 *
 * null leaves it to the form type, by its "empty_data" (what a form of an entity needing arguments has to do).
 */
interface DoctrineEntityFactory
{
    public function create(AsCrud $crud, DoctrineSource $source): ?object;
}
