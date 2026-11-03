<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\CommandPalette\CommandPaletteCatalog;
use Byfareska\Crud\CommandPalette\PaletteRecord;
use Byfareska\Crud\CommandPalette\ScopedRecordSource;
use Byfareska\Crud\Driver\CrudDrivers;
use JsonSerializable;
use Stringable;

/**
 * The records of every #[AsCrud] resource that can be read, found for the command palette by asking its list for
 * what was typed - the same way the search field of the list does, whatever the driver.
 */
final readonly class CrudPaletteRecords implements ScopedRecordSource
{
    public function __construct(
        private CrudRegistry $registry,
        private CrudDrivers $drivers,
        private CrudAccess $access,
        private IdMapper $ids,
        private RecordGuards $guards,
    ) {
    }

    public function search(string $query, int $limit): array
    {
        return $this->searchIn($query, $limit, null);
    }

    /**
     * The records of the resources of the panel the palette is in (null: of every panel) - the others not asked.
     */
    public function searchIn(string $query, int $limit, ?string $scope): array
    {
        $records = [];
        foreach ($this->registry->all() as $crud) {
            // One's own record is no search: CrudPaletteSource offers its page.
            if (
                !$crud->own
                && CommandPaletteCatalog::inScope($crud->scope, $scope)
                && $crud->inPalette(CrudOperation::Read)
                && $this->access->isGranted($crud)
            ) {
                array_push($records, ...$this->recordsOf($crud, $query, $limit));
            }
        }

        return $records;
    }

    /**
     * @return list<PaletteRecord>
     */
    private function recordsOf(AsCrud $crud, string $query, int $limit): array
    {
        // Named after the first column (or the title of the resource) and described by the next one, like a row of the
        // list.
        $names = $crud->titleFields();
        if ($names === []) {
            return [];
        }
        $description = array_find(
            array_column($crud->columns, 'field'),
            static fn (string $field): bool => !in_array($field, $names, true),
        );

        $records = [];
        $fields = array_values(array_unique(array_filter([
            'id',
            ...$names,
            $description,
            ...$this->guards->fields($crud),
        ])));
        foreach ($this->find($crud, $query, $limit, $fields) as $item) {
            $id = $this->ids->toId($item['id'] ?? null);
            $title = $crud->titleOf($item);
            if ($id === null || $title === null) {
                continue;
            }
            // Not offered when it cannot be opened (RecordGuard).
            if (!$this->guards->allows(CrudOperation::Read, new RecordView($crud, $id, $item, RecordScreen::List))) {
                continue;
            }

            $records[] = new PaletteRecord(
                key: $crud->key('record.' . $id),
                group: $crud->key('title'),
                name: $title,
                description: $description === null ? '' : $this->text($item[$description] ?? null),
                icon: $crud->icon,
                routeName: $crud->route('show'),
                routeParameters: ['id' => $id],
                scope: $crud->scope,
            );
        }

        return $records;
    }

    /**
     * @param list<string> $fields
     *
     * @return list<array<string, mixed>>
     */
    private function find(AsCrud $crud, string $query, int $limit, array $fields): array
    {
        $driver = $this->drivers->of($crud);
        $form = $driver->listForm($crud);

        $data = [];
        foreach (
            [
            (string) $crud->search => $query,
            'perPage' => (string) $limit,
            'fields' => $fields,
            ] as $field => $value
        ) {
            if ($form->has($field)) {
                $data[$field] = $value;
            }
        }

        $form->submit($data);
        // A text the list would not take either (too long, say) finds nothing.
        if (!$form->isValid()) {
            return [];
        }

        try {
            return array_slice($driver->list($crud, $form)->items, 0, $limit);
        } catch (CrudFailure) {
            return [];
        }
    }

    private function text(mixed $value): string
    {
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        return is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
    }
}
