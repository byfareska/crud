<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

/**
 * What a CrudFormFiller made of what was pasted: the values of fields of the form, by their names, and what is to be
 * said of them - or why nothing could be made of it.
 */
final readonly class CrudFill
{
    /**
     * @param array<string, mixed> $values by the names of the form's fields
     * @param list<array{key: string, parameters: array<string, string>}> $notes translation keys with their parameters
     * @param string|null $error why nothing could be made, in the filler's words
     */
    public function __construct(
        public array $values = [],
        public array $notes = [],
        public ?string $error = null,
    ) {
    }
}
