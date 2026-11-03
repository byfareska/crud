<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * Changes the query of the list of a {@see DoctrineSource} after its scopes, search, filters and order: a join that
 * reads a relation with the records, a full-text search, records only the list leaves out. A service, autoconfigured.
 * The palette finds records through the list, so it goes by it too; a record opened by its address does not - which
 * entities there are at all, for the list and a record found by its id alike, is a {@see DoctrineScope}.
 *
 * An order it adds (addOrderBy()) comes after that of the headings; then the list is ordered by the id.
 */
interface DoctrineListExtension
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.doctrine_list_extension';

    /** The resource of an extension of every resource. */
    public const string EVERY = '*';

    /** The name of the resource whose list it changes, as in #[AsCrud]; EVERY for all. */
    public function resource(): string;

    public function apply(DoctrineListQuery $query): void;
}
