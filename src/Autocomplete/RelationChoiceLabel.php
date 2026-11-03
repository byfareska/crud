<?php

declare(strict_types=1);

namespace Byfareska\Crud\Autocomplete;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The text an entity is offered by in a relation filter searched as one types - "Kowalska, Anna (PL123)" - instead of
 * its __toString(). A service, autoconfigured; the first that supports the entity says it.
 */
#[AutoconfigureTag(self::TAG)]
interface RelationChoiceLabel
{
    public const string TAG = 'byfareska_crud.relation_choice_label';

    public function supports(object $entity): bool;

    public function label(object $entity): string;
}
