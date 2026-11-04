<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Binding;

use Byfareska\Crud\Files\FileItem;

/**
 * How a field of files reads the value of the record and writes it back: a reference in a column (ColumnBinding),
 * an entity of a file (RecordBinding), the items of a gallery (ItemCollectionBinding) - or the application's own, given
 * as the "binding" of the field (CrudFileType).
 */
interface FileBinding
{
    /**
     * The files the value of the record holds, in their order.
     *
     * @return list<FileItem>
     */
    public function read(mixed $value): array;

    /**
     * The value the record gets for the files of the field.
     *
     * @param list<FileItem> $items in the order of the field
     * @param mixed $previous what the record held: the objects it had are kept, those of the files still there
     */
    public function write(array $items, mixed $previous): mixed;
}
