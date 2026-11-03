<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * Orders the list by the field of a {@see FieldSort}, the relation joined once.
 */
final readonly class FieldSortHandler implements DoctrineSortHandler
{
    public function apply(DoctrineSort $sort, DoctrineListQuery $query, bool $descending): void
    {
        assert($sort instanceof FieldSort);
        $query->query->addOrderBy($query->path($sort->field), $descending ? 'DESC' : 'ASC');
    }
}
