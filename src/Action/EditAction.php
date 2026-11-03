<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * The way to the edit form of a record - one of the built-in actions, offered to a resource that can update a
 * record; the main button of the details page and the preview drawer. Declared in AsCrud::$actions it takes the place
 * of the built-in one there: `new EditAction(inline: true)`. Not offered on the edit form itself, nor for a record its
 * guards do not let be updated (RecordGuard).
 */
final readonly class EditAction implements RecordAction
{
    public const string NAME = 'edit';

    /**
     * @param string $icon a Bootstrap Icons name
     * @param string|null $role only somebody with this role is offered the action
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     */
    public function __construct(
        public string $icon = 'pencil',
        public ?string $role = null,
        public bool $inline = false,
        public string $name = self::NAME,
    ) {
    }

    public function fields(): array
    {
        return [];
    }

    public function handler(): string
    {
        return EditActionHandler::class;
    }
}
