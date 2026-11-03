<?php

declare(strict_types=1);

namespace Byfareska\Crud\Autocomplete;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The texts of the RelationChoiceLabel services: of the first that supports an entity.
 */
final readonly class RelationChoiceLabels
{
    /**
     * @param iterable<RelationChoiceLabel> $labels
     */
    public function __construct(
        #[AutowireIterator(RelationChoiceLabel::TAG)]
        private iterable $labels = [],
    ) {
    }

    /** The text of the entity; null = none of them says it. */
    public function of(object $entity): ?string
    {
        foreach ($this->labels as $label) {
            if ($label->supports($entity)) {
                return $label->label($entity);
            }
        }

        return null;
    }
}
