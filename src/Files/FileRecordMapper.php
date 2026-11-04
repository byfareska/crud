<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * What a file is, for an object of the application standing for one - an entity of a library - and the object of a
 * file: what a field related to such entities (binding "record") and a list of their values go through. The bundle's
 * reads a {@see FileRecord}; one of the application's (an entity of another bundle, a media of a DAM) is a service
 * implementing this, autoconfigured.
 */
interface FileRecordMapper
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.file_record_mapper';

    public function supports(object $record): bool;

    public function toStoredFile(object $record): StoredFile;

    /**
     * The object of a file, of a class this mapper supports; null = none.
     *
     * @param class-string $class what the field relates to
     */
    public function find(string $class, FileReference $reference): ?object;
}
