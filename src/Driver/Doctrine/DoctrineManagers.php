<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

/**
 * The entity manager an entity of a resource is read and written with: the one its source names
 * (DoctrineSource::$manager - a database of a tenant, given by a CrudDefinitionExtension), when that one maps the
 * entity, else the one its mapping is in. The related entities of a resource - its relation filters, the choices
 * of its form, ?with[category]=5 - come from the same database as its records, not from the first manager that maps
 * them.
 */
final readonly class DoctrineManagers
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    /**
     * @param class-string $entity the entity of the resource, or one related to it; null = the entity of the resource
     *
     * @throws LogicException the entity is none of Doctrine ORM
     */
    public function of(AsCrud $crud, ?string $entity = null): EntityManagerInterface
    {
        $source = $crud->source instanceof DoctrineSource ? $crud->source : null;
        $entity ??= $source->entity ?? throw new LogicException(sprintf(
            'CRUD resource "%s" is not one of entities.',
            $crud->name,
        ));

        if ($source?->manager !== null) {
            $named = $this->doctrine->getManager($source->manager);
            if ($named instanceof EntityManagerInterface && !$named->getMetadataFactory()->isTransient($entity)) {
                return $named;
            }
        }

        return $this->forClass($entity);
    }

    /**
     * The manager whose mapping has the entity.
     *
     * @param class-string $entity
     *
     * @throws LogicException the entity is none of Doctrine ORM
     */
    public function forClass(string $entity): EntityManagerInterface
    {
        $manager = $this->doctrine->getManagerForClass($entity);

        return $manager instanceof EntityManagerInterface
            ? $manager
            : throw new LogicException(sprintf('"%s" is not an entity of Doctrine ORM.', $entity));
    }
}
