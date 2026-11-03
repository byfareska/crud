<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CrudDrivers;

/**
 * The bundle's {@see CrudRecords}: through the driver of the resource.
 *
 * @phpstan-import-type RecordDetails from CrudRecords
 */
final readonly class DefaultCrudRecords implements CrudRecords
{
    public function __construct(
        private CrudDrivers $drivers,
    ) {
    }

    /**
     * @return array<string, mixed>
     *
     * @throws CrudFailure
     */
    public function read(AsCrud $crud, string $id): array
    {
        return $this->drivers->of($crud)->read($crud, $id);
    }

    /**
     * A record as the preview drawer and the details page show it: what the driver reads, one row per property (or
     * per entry of "details" in #[AsCrud]) - but those it keeps from sight (AsCrud::showsInDetails(): "hidden", and
     * without "details" what looks secret, a password hash). The title is null when the record has no name to go by.
     * "item" still has every property: what a guard, an action or a section of the page goes by.
     *
     * @return RecordDetails
     *
     * @throws CrudFailure
     */
    public function details(AsCrud $crud, string $id): array
    {
        $item = $this->read($crud, $id);

        $columns = array_column($crud->columns, null, 'field');
        $details = $crud->details !== [] ? $crud->details : array_map(
            static fn (string $field): Column => $columns[$field] ?? $crud->column($field),
            array_map(strval(...), array_keys($item)),
        );
        $details = array_values(array_filter(
            $details,
            static fn (Column $column): bool => $crud->showsInDetails($column->field),
        ));

        return [
            'id' => $id,
            // Named after its first column (or the title of the resource), like its row in the list.
            'title' => $crud->titleOf($item),
            'rows' => array_map(
                static fn (Column $column): array => ['column' => $column, 'value' => $item[$column->field] ?? null],
                $details,
            ),
            'item' => $item,
        ];
    }

    /**
     * A record as its guards and actions get it outside the list: every property, when the resource can read one.
     *
     * @throws CrudFailure
     */
    public function view(AsCrud $crud, string $id, RecordScreen $screen): RecordView
    {
        return new RecordView($crud, $id, $crud->can(CrudOperation::Read) ? $this->read($crud, $id) : [], $screen);
    }

    /**
     * @throws CrudFailure
     */
    public function delete(AsCrud $crud, string $id): void
    {
        $this->drivers->of($crud)->delete($crud, $id);
    }
}
