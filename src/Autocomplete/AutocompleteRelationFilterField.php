<?php

declare(strict_types=1);

namespace Byfareska\Crud\Autocomplete;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\RelationFilterField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A relation filtered by a field to search in (symfony/ux-autocomplete): only the entity chosen is loaded, the others
 * are asked of {@see RelationAutocompleter} as one types. What CrudBundle uses when the package is there.
 */
final readonly class AutocompleteRelationFilterField implements RelationFilterField
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public function options(AsCrud $crud, string $field, string $entity): array
    {
        return [
            'class' => $entity,
            'choice_lazy' => true,
            'autocomplete' => true,
            'autocomplete_url' => $this->urls->generate('ux_autocomplete', ['alias' => RelationAutocompleter::ALIAS]),
            // Signed by the package: the address cannot ask for another resource.
            'extra_options' => [RelationAutocompleter::RESOURCE => $crud->name, RelationAutocompleter::FIELD => $field],
        ];
    }
}
