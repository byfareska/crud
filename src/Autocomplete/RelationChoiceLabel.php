<?php

declare(strict_types=1);

namespace Byfareska\Crud\Autocomplete;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * The text an entity is named by where it is another record's relation - "Kowalska, Anna (PL123)" - instead of the
 * title of its resource or its __toString(): a cell of a list, the details, a choice of a form or a filter, a result of
 * the autocomplete (DoctrineRecordLabels). Needs no symfony/ux-autocomplete. A service, autoconfigured; the first that
 * supports the entity says it.
 */
#[AutoconfigureTag(self::TAG)]
interface RelationChoiceLabel
{
    public const string TAG = 'byfareska_crud.relation_choice_label';

    public function supports(object $entity): bool;

    public function label(object $entity): string;
}
