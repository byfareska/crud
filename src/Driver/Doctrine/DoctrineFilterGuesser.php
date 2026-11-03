<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * What filter a field named in DoctrineSource::$filters is, unless it is given one: by default
 * {@see MappingFilterGuesser}, from the mapping. An application changes it by aliasing this interface to a service of
 * its own (which may ask the default one for the fields it does not care about).
 */
interface DoctrineFilterGuesser
{
    /**
     * @param ClassMetadata<object> $metadata of the entity
     * @param string $field a field or a relation of the entity
     * @param string|null $type the form type the source names for it; null = to be guessed too
     */
    public function guess(AsCrud $crud, ClassMetadata $metadata, string $field, ?string $type): DoctrineFilter;
}
