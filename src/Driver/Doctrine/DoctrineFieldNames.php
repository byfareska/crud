<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Translation\ResourceFieldNames;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The fields and relations of the entity of a Doctrine resource, and the fields of its filters: what its guessed
 * forms, its details and its filters are labelled by.
 */
final readonly class DoctrineFieldNames implements ResourceFieldNames
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    public function of(AsCrud $crud): array
    {
        $source = $crud->source;
        if (!$source instanceof DoctrineSource) {
            return [];
        }
        $manager = $this->doctrine->getManagerForClass($source->entity);
        if (!$manager instanceof EntityManagerInterface) {
            return [];
        }
        $metadata = $manager->getClassMetadata($source->entity);

        return array_values(array_filter([
            ...$metadata->getFieldNames(),
            ...$metadata->getAssociationNames(),
            ...array_map(
                static fn (int|string $key, mixed $filter): ?string => is_string($key)
                    ? $key
                    : (is_string($filter) ? $filter : null),
                array_keys($source->filters),
                $source->filters,
            ),
        ], is_string(...)));
    }
}
