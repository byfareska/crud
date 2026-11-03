<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes what the forms and the deleting of a resource did to its entity. The bundle's is {@see FlushingPersister}:
 * persist (a new one) or remove, then flush. Alias the interface (or decorate it) to write another way - a method of
 * the domain in place of removing (`$order->cancel()`), a repository or a service of the application, a record of the
 * change next to it. It runs in the transaction of the save or the delete (RecordTransaction), between RecordSaving
 * and RecordSaved (RecordDeleting and RecordDeleted).
 *
 * What it throws goes through the {@see DoctrineExceptionMapper}: a CrudFailure it throws itself is shown as it is.
 */
interface DoctrinePersister
{
    /**
     * @param bool $created a new entity (the add form), not one the manager has yet
     */
    public function save(AsCrud $crud, object $entity, bool $created, EntityManagerInterface $manager): void;

    public function delete(AsCrud $crud, object $entity, EntityManagerInterface $manager): void;
}
