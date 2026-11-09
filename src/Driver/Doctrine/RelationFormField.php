<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * The options of a relation of a guessed Doctrine form (the type guessed from the mapping: EntityType): a choice of
 * every related entity (ChoiceRelationFormField), or - with symfony/ux-autocomplete - a field to search in, which
 * loads only the entity chosen (AutocompleteRelationFormField). Alias it to choose otherwise for every resource.
 */
interface RelationFormField
{
    /**
     * @param string $field the relation of the entity of the resource
     * @param string $entity the related entity
     * @param object|null $record the entity being edited; null in the add form
     * @param string|null $id its id, as in the address
     *
     * @return array<string, mixed>
     */
    public function options(AsCrud $crud, string $field, string $entity, ?object $record, ?string $id): array;
}
