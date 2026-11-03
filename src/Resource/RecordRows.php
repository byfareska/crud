<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The attributes of the row of a record in the list, of every {@see RecordRow} of its resource: a later one's value
 * wins, but the classes of all are put together.
 */
final readonly class RecordRows
{
    /**
     * @param iterable<RecordRow> $rows
     */
    public function __construct(
        #[AutowireIterator(RecordRow::TAG)]
        private iterable $rows = [],
    ) {
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    public function attributes(RecordView $record): array
    {
        $attributes = [];
        $classes = [];
        foreach ($this->of($record->crud) as $row) {
            $own = $row->attributes($record);
            if (isset($own['class']) && is_string($own['class']) && trim($own['class']) !== '') {
                $classes[] = trim($own['class']);
            }
            unset($own['class'], $own['id']);
            $attributes = array_replace($attributes, $own);
        }
        if ($classes !== []) {
            $attributes['class'] = implode(' ', $classes);
        }

        return $attributes;
    }

    /**
     * What the rows of a resource need of an item of the list.
     *
     * @return list<string>
     */
    public function fields(AsCrud $crud): array
    {
        $fields = [];
        foreach ($this->of($crud) as $row) {
            array_push($fields, ...$row->fields());
        }

        return array_values(array_unique($fields));
    }

    /**
     * @return iterable<RecordRow>
     */
    private function of(AsCrud $crud): iterable
    {
        foreach ($this->rows as $row) {
            if ($row->resource() === $crud->name || $row->resource() === RecordRow::EVERY) {
                yield $row;
            }
        }
    }
}
