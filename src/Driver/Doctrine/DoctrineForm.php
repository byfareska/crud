<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use InvalidArgumentException;

/**
 * The add or the edit form of a {@see DoctrineSource}: a form type of the application with options of its own, or the
 * fields guessed from the mapping. Given as "form" for both, or as "createForm" / "updateForm" for one of them -
 * a password only when a user is added, a SKU that is not changed once given, other validation groups:
 *
 *     new DoctrineSource(
 *         User::class,
 *         createForm: new DoctrineForm(fields: ['email', 'plainPassword', 'roles']),
 *         updateForm: new DoctrineForm(UserType::class, ['validation_groups' => ['Default', 'edit']]),
 *     )
 *
 * The form is given the entity: a new one, or the one edited. The live component has its own protection against
 * cross-site requests, so the form has none ("csrf_protection" is always false).
 */
final readonly class DoctrineForm
{
    /**
     * @param class-string|null $type the form type; null = the fields of $fields, their types guessed from the mapping
     * @param array<string, mixed> $options of the form type (of the form of the guessed fields): "validation_groups",
     *     options a type of the application defines
     * @param list<string> $fields what the guessed form has; empty = every mapped field and owning relation but the id
     */
    public function __construct(
        public ?string $type = null,
        public array $options = [],
        public array $fields = [],
    ) {
        if ($type !== null && $fields !== []) {
            throw new InvalidArgumentException(sprintf(
                'A form of "%s" has its fields itself: "fields" are those of a form guessed from the mapping.',
                $type,
            ));
        }
        if (!array_all($fields, static fn (mixed $field): bool => is_string($field))) {
            throw new InvalidArgumentException('The fields of a guessed form are a list of their names.');
        }
    }
}
