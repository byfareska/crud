<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Binding;

use Byfareska\Crud\Files\FileItem;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\StoredFile;
use LogicException;

/**
 * The files of a field as objects of files - the entity of the library (CrudFileRecord, the application's Media):
 * a relation to one (ManyToOne), or to many (ManyToMany, whose order is not kept: attachments, not a gallery). An
 * object is found by its reference through the FileRecordMappers; one the record had is kept.
 */
final readonly class RecordBinding implements FileBinding
{
    /**
     * @param class-string $class the class of the objects of files
     */
    public function __construct(
        private FileRecordMappers $mappers,
        private string $class,
        private bool $multiple = false,
    ) {
    }

    public function read(mixed $value): array
    {
        $items = [];
        foreach ($this->objects($value) as $record) {
            $file = $this->mappers->toStoredFile($record);
            if ($file instanceof StoredFile) {
                $items[] = new FileItem($file->reference);
            }
        }

        return $items;
    }

    public function write(array $items, mixed $previous): mixed
    {
        $had = [];
        foreach ($this->objects($previous) as $record) {
            $file = $this->mappers->toStoredFile($record);
            if ($file instanceof StoredFile) {
                $had[(string) $file->reference] = $record;
            }
        }
        $records = [];
        foreach ($items as $item) {
            $records[] = $had[(string) $item->reference]
                ?? $this->mappers->find($this->class, $item->reference)
                ?? throw new LogicException(sprintf(
                    'No %s is the file "%s": the library of its storage did not keep it (byfareska_crud.files.'
                    . 'storages.<name>.library), or no FileRecordMapper finds it.',
                    $this->class,
                    $item->reference,
                ));
        }

        return $this->multiple ? $records : ($records[0] ?? null);
    }

    /**
     * @return list<object>
     */
    private function objects(mixed $value): array
    {
        if (is_object($value) && !is_iterable($value)) {
            return [$value];
        }

        return is_iterable($value)
            ? array_values(array_filter([...$value], is_object(...)))
            : [];
    }
}
