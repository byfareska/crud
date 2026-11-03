<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

/**
 * The DTO of an edit form that takes what is structured in the record itself (a list of objects, a nested value) -
 * the plain values are copied onto its same-named properties before, as for any DTO (CrudRecords).
 */
interface FillsFromRecord
{
    /**
     * @param object $record what the "read" use case returned
     */
    public function fillFrom(object $record): void;
}
