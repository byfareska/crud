<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * A relation of a guessed form as a choice of every related entity - for a few of them; see {@see RelationFormField}.
 * The choices are named as the lists name them (no __toString() needed). Which entities there are - those the scopes
 * of the related resource leave, never the record being edited - is the query_builder the driver gives the field
 * (DoctrineDriver, DoctrineRelations::choices()).
 */
final readonly class ChoiceRelationFormField implements RelationFormField
{
    public function __construct(
        private ?DoctrineRecordLabels $labels = null,
    ) {
    }

    public function options(AsCrud $crud, string $field, string $entity, ?object $record, ?string $id): array
    {
        return $this->labels instanceof DoctrineRecordLabels ? ['choice_label' => $this->labels->of(...)] : [];
    }
}
