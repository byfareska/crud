<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * Adds attributes to the row of a record in the list: a class marking what is out of stock or overdue, a data
 * attribute for a Stimulus controller of the application. A service, autoconfigured; every one of the resource adds
 * to the row, their classes put together.
 */
interface RecordRow
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.record_row';

    /** The resource of a row of every resource. */
    public const string EVERY = '*';

    /** The name of the resource whose rows it adds to, as in #[AsCrud]; EVERY for all. */
    public function resource(): string;

    /**
     * What the list asks of every item for it besides the columns.
     *
     * @return list<string>
     */
    public function fields(): array;

    /**
     * The attributes of the row (<tr>) of the record, escaped by the theme; "id" is the theme's own.
     *
     * @return array<string, string|int|bool|null>
     */
    public function attributes(RecordView $record): array;
}
