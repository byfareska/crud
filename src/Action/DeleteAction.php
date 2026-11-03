<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * Deleting a record, once confirmed - one of the built-in actions, offered to a resource that can delete a record:
 * in the list, the preview drawer, on the details page and the edit form (then back to the list). Declared in
 * AsCrud::$actions it takes the place of the built-in one there, e.g. with texts of its own:
 * `new DeleteAction(message: 'app.invoice.delete')`. Not offered for a record its guards do not let be deleted
 * (RecordGuard); RecordDeleting may still refuse.
 */
final readonly class DeleteAction implements RecordAction, AsksForInput
{
    public const string NAME = 'delete';

    /**
     * @param string $message translation key of the question
     * @param string $hint translation key of what is said under it
     * @param string $icon a Bootstrap Icons name
     * @param string|null $role only somebody with this role is offered the action
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     * @param ConfirmInput|null $input a field of the question: a reason of the delete, in RecordDeleting::$input
     */
    public function __construct(
        public string $message = 'crud.confirm_delete.message',
        public string $hint = 'crud.confirm_delete.hint',
        public string $icon = 'trash',
        public ?string $role = null,
        public bool $inline = false,
        public string $name = self::NAME,
        public ?ConfirmInput $input = null,
    ) {
    }

    public function fields(): array
    {
        return [];
    }

    public function handler(): string
    {
        return DeleteActionHandler::class;
    }
}
