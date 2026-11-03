<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use ReflectionClass;

/**
 * The bundle's {@see DoctrineEntityFactory}: the entity's constructor, without arguments; null when it needs some
 * (the form type makes it then, by its "empty_data").
 */
final readonly class ConstructorEntityFactory implements DoctrineEntityFactory
{
    public function create(AsCrud $crud, DoctrineSource $source): ?object
    {
        $class = new ReflectionClass($source->entity);
        if (($class->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
            return null;
        }

        return $class->newInstance();
    }
}
