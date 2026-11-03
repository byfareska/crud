<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * Which entities of a resource of a {@see DoctrineSource} there are for the panel at all: "only my records", "those of
 * the last week", by a join of another entity too. A service, autoconfigured.
 *
 * Narrows the list (and so the command palette and what bulk actions choose from), a record found by its id (its
 * details, the preview, the edit form, deleting: any other is not found) and the entities a relation filter of another
 * list offers as one types, when they are this resource's. A choice of every related entity (no
 * symfony/ux-autocomplete) and the relation fields of a form are not narrowed: that is their form type's
 * "query_builder". What only the list leaves out is a {@see DoctrineListExtension}; who may do what with a record
 * found is a RecordGuard.
 */
interface DoctrineScope
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.doctrine_scope';

    /** The resource of a scope of every resource. */
    public const string EVERY = '*';

    /** The name of the resource whose entities it narrows, as in #[AsCrud]; EVERY for all. */
    public function resource(): string;

    /**
     * Narrows the query: conditions (andWhere()), joins of its own - not the order, not the page. A
     * {@see DoctrineListQuery} when it is the list.
     */
    public function apply(DoctrineQuery $query): void;
}
