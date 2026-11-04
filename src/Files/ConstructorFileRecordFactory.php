<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Makes the entity of a library with its constructor, given the StoredFile: the bundle's entities (and those extending
 * {@see Doctrine\Mapped\AbstractFileRecord}) are made so.
 */
final readonly class ConstructorFileRecordFactory implements FileRecordFactory
{
    public function create(string $class, StoredFile $file): object
    {
        return new $class($file);
    }
}
