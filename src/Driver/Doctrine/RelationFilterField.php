<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * The options of the EntityType a relation is filtered by: by default a choice of every related entity
 * ({@see ChoiceRelationFilterField}); with symfony/ux-autocomplete a field to search in, asking the server as one types
 * (Autocomplete\AutocompleteRelationFilterField). Either way the driver gives the field a query_builder of the entities
 * the scopes of the related resource leave (DoctrineRelations), unless these options or the filter have one.
 */
interface RelationFilterField
{
    /** The key of "extra_options" the driver tells the name of the filter by, to a field searched as one types. */
    public const string FILTER = 'filter';

    /**
     * Options of a FieldFilter of a relation by which the application chooses the entities or how they are shown
     * itself: `new FieldFilter('owner', options: ['query_builder' => ..., 'choice_label' => ...])`. The field searched
     * as one types (RelationAutocompleter) would follow none of them - it would offer other entities, named otherwise,
     * and with "options_as_html" its texts would be taken as HTML - so a filter with any of them is a plain choice by
     * those options instead (in the scopes of the related resource, unless it has a query_builder of its own).
     */
    public const array OWN_CHOICES = [
        'query_builder',
        'choices',
        'choice_loader',
        'choice_filter',
        'choice_label',
        'choice_value',
        'options_as_html',
    ];

    /**
     * @param string $field the relation of the resource's entity
     * @param class-string $entity the related entity
     *
     * @return array<string, mixed>
     */
    public function options(AsCrud $crud, string $field, string $entity): array;
}
