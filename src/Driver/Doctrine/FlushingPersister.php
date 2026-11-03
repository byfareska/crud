<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The bundle's {@see DoctrinePersister}: persist or remove, then flush.
 */
final readonly class FlushingPersister implements DoctrinePersister
{
    public function save(AsCrud $crud, object $entity, bool $created, EntityManagerInterface $manager): void
    {
        if ($created) {
            $manager->persist($entity);
        }
        $manager->flush();
    }

    public function delete(AsCrud $crud, object $entity, EntityManagerInterface $manager): void
    {
        $manager->remove($entity);
        $manager->flush();
    }
}
