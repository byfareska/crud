<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * The way to the details page of a record - one of the built-in actions, offered to a resource that can read a
 * record. Declared in AsCrud::$actions it takes the place of the built-in one there:
 * `new DetailsAction(inline: false)`.
 * Not offered on the details page itself, nor for a record its guards do not let be read (RecordGuard).
 */
final readonly class DetailsAction implements RecordAction
{
    public const string NAME = 'details';

    /**
     * @param string $icon a Bootstrap Icons name
     * @param string|null $role only somebody with this role is offered the action
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     */
    public function __construct(
        public string $icon = 'eye',
        public ?string $role = null,
        public bool $inline = true,
        public string $name = self::NAME,
    ) {
    }

    public function fields(): array
    {
        return [];
    }

    public function handler(): string
    {
        return DetailsActionHandler::class;
    }
}
