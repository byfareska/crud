<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * A relation filtered by a choice of every related entity - for a few of them; see {@see RelationFilterField}.
 */
final readonly class ChoiceRelationFilterField implements RelationFilterField
{
    public function __construct(
        private ?DoctrineRecordLabels $labels = null,
    ) {
    }

    public function options(AsCrud $crud, string $field, string $entity): array
    {
        return $this->labels instanceof DoctrineRecordLabels
            ? ['class' => $entity, 'choice_label' => $this->labels->of(...)]
            : ['class' => $entity];
    }
}
