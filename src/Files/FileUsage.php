<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Where a file is used: a field of a record of a resource.
 */
final readonly class FileUsage
{
    /**
     * @param string $resource the name of the resource (#[AsCrud])
     * @param string $id of the record, as in its address
     * @param string $field "gallery", or "section.field" in a section of the form
     */
    public function __construct(
        public string $resource,
        public string $id,
        public string $field,
    ) {
    }
}
