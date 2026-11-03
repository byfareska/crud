<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * The records of a resource are not listed on a page of their own but in the list of another resource, filtered by
 * them - the folders in the list of the files. The resource then has no list page, no entry in the menu and none in
 * the command palette for its list; the way back from its forms and its details page (and after a save) is the list
 * of the other resource with the filter set to the record.
 */
final readonly class CrudListedIn
{
    /**
     * @param string $resource the resource whose list shows these records
     * @param string $filter the filter of that list that takes the id of a record
     */
    public function __construct(
        public string $resource,
        public string $filter,
    ) {
    }
}
