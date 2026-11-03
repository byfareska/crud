<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * The bundle's order by a field of the entity or of a related one ("category.name"): what a field named in "sortable"
 * is. Made by {@see FieldSortHandler}.
 */
final readonly class FieldSort implements DoctrineSort
{
    /**
     * @param string $field of the entity, or of a related entity ("category.name")
     */
    public function __construct(public string $field)
    {
    }

    public function handler(): string
    {
        return FieldSortHandler::class;
    }
}
