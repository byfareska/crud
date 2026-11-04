<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Makes the entity of a library for a file just uploaded. The bundle's calls the constructor of the entity with the
 * StoredFile ({@see Doctrine\ConstructorFileRecordFactory}); an entity made otherwise has a service of its own, named
 * in byfareska_crud.files.record_factory.
 */
interface FileRecordFactory
{
    /**
     * @param class-string $class byfareska_crud.files.entity
     */
    public function create(string $class, StoredFile $file): object;
}
