<?php

declare(strict_types=1);

namespace Byfareska\Crud\Autocomplete;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineRecordLabels;
use Byfareska\Crud\Driver\Doctrine\RelationFormField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A relation of a guessed form as a field to search in (symfony/ux-autocomplete): only the entity chosen is loaded,
 * the others are found as one types by RelationAutocompleter - in the scopes of the related resource, for somebody
 * who may add or edit records of this one, named as the lists name them, the record being edited never its own
 * parent. Used for every resource once symfony/ux-autocomplete is installed; see {@see RelationFormField}.
 */
final readonly class AutocompleteRelationFormField implements RelationFormField
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private ?DoctrineRecordLabels $labels = null,
    ) {
    }

    public function options(AsCrud $crud, string $field, string $entity, ?object $record, ?string $id): array
    {
        $extra = [
            RelationAutocompleter::RESOURCE => $crud->name,
            RelationAutocompleter::FIELD => $field,
            RelationAutocompleter::FORM => '1',
        ];
        if ($id !== null) {
            $extra[RelationAutocompleter::RECORD] = $id;
        }
        $options = [
            'choice_lazy' => true,
            'autocomplete' => true,
            'autocomplete_url' => $this->urls->generate(
                'crud_autocomplete_' . $crud->scope,
                ['alias' => RelationAutocompleter::ALIAS],
            ),
            // Signed by the package: the address cannot ask for another resource, field or record.
            'extra_options' => $extra,
        ];
        if ($this->labels instanceof DoctrineRecordLabels) {
            $options['choice_label'] = $this->labels->of(...);
        }

        return $options;
    }
}
