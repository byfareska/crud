<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * Who may do what with one record, beyond the role of its resource: "a salesperson edits only their own customers",
 * "a paid invoice is not deleted". A service, autoconfigured; every guard of the resource has to allow it.
 *
 * Asked for reading (the details page, the preview, the other pages of the record), updating (the edit form, also
 * when it is saved) and deleting; a record is still listed - what the list shows is up to its source (for entities a
 * DoctrineScope narrows the list and every record found by its id alike). What is refused is not offered either: the
 * buttons of the built-in actions (DetailsAction, EditAction, DeleteAction) go away.
 *
 * The record is what the place has of it: an item of the list there (with what fields() asks for), every property
 * read elsewhere - empty on the edit form of a resource that cannot read one.
 */
interface RecordGuard
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.record_guard';

    /** The resource of a guard of every resource. */
    public const string EVERY = '*';

    /** The name of the resource whose records it guards, as in #[AsCrud]; EVERY for all. */
    public function resource(): string;

    /**
     * What the guard needs of an item of the list besides its columns, e.g. "ownerId".
     *
     * @return list<string>
     */
    public function fields(): array;

    /** Read, Update or Delete; never List nor Create - there is no record yet. */
    public function allows(CrudOperation $operation, RecordView $record): bool;
}
