<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * The single-record operations of a resource, addressed by the id as it appears in a URL, whatever its driver: what
 * the pages, the preview, the guards and the deleting read a record by. The bundle's is {@see DefaultCrudRecords}
 * (through the driver of the resource); decorate it (#[AsDecorator(CrudRecords::class)]) to add to what a record
 * shows - a value computed for every resource, what another system knows of it - or to keep it read once a request.
 *
 * @phpstan-type RecordDetails array{
 *     id: string,
 *     title: string|null,
 *     rows: list<array{column: Column, value: mixed}>,
 *     item: array<string, mixed>,
 * }
 */
interface CrudRecords
{
    /**
     * Every property of a record, as its driver reads it.
     *
     * @return array<string, mixed>
     *
     * @throws CrudFailure
     */
    public function read(AsCrud $crud, string $id): array;

    /**
     * A record as the preview drawer and the details page show it: one row per property shown (see
     * AsCrud::showsInDetails()), its title, and every property ("item").
     *
     * @return RecordDetails
     *
     * @throws CrudFailure
     */
    public function details(AsCrud $crud, string $id): array;

    /**
     * A record as its guards and actions get it outside the list: every property, when the resource can read one.
     *
     * @throws CrudFailure
     */
    public function view(AsCrud $crud, string $id, RecordScreen $screen): RecordView;

    /**
     * Deletes it through the driver, nothing else - the guards, the events and the transaction are RecordDeletion's.
     *
     * @throws CrudFailure
     */
    public function delete(AsCrud $crud, string $id): void;
}
