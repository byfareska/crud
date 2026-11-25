<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * Which entities of a resource of a {@see DoctrineSource} there are for the panel at all: "only my records", "those of
 * the last week", by a join of another entity too. A service, autoconfigured.
 *
 * Narrows the list (and so the command palette and what bulk actions choose from), a record found by its id (its
 * details, the preview, the edit form, deleting: any other is not found) and, when they are this resource's, the
 * entities a relation of a guessed form or a relation filter of another list offers - as a choice or as one types -
 * and takes when sent (DoctrineRelations): an id of another entity sent by hand is refused like one never offered. A
 * record keeps the entity it has in a relation even if the scopes no longer leave it, which cannot be newly chosen
 * though. Not narrowed: a form type of the application's own (DoctrineSource "form"), a filter or a field given a
 * "query_builder" of its own - that query_builder decides. What only the list leaves out is a
 * {@see DoctrineListExtension}; who may do what with a record found is a RecordGuard.
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
