<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * An entity of the application that is a file of a library: the bundle's {@see Doctrine\Mapped\AbstractFileRecord}
 * and anything extending it, or an entity of its own that says what file it is. Doctrine's library finds it by the
 * fields of byfareska_crud.files.entity_fields; a field of files may relate to it (binding "record").
 *
 * An entity that cannot implement this (of another bundle) has a FileRecordMapper of its own instead.
 */
interface FileRecord
{
    public function storedFile(): StoredFile;
}
