<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * The options of the EntityType a relation is filtered by: by default a choice of every related entity
 * ({@see ChoiceRelationFilterField}); with symfony/ux-autocomplete a field to search in, asking the server as one types
 * (Autocomplete\AutocompleteRelationFilterField).
 */
interface RelationFilterField
{
    /** The key of "extra_options" the driver tells the name of the filter by, to a field searched as one types. */
    public const string FILTER = 'filter';

    /**
     * @param string $field the relation of the resource's entity
     * @param class-string $entity the related entity
     *
     * @return array<string, mixed>
     */
    public function options(AsCrud $crud, string $field, string $entity): array;
}
