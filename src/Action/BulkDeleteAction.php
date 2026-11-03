<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * Deleting the records chosen, once confirmed: `bulkActions: [new BulkDeleteAction()]`. Each one goes the way of one
 * deleted on its own (RecordGuard, RecordDeleting, RecordDeleted); what is refused stays and is counted in the toast.
 * Offered to a resource that can delete a record.
 */
final readonly class BulkDeleteAction implements BulkAction, AsksForInput
{
    public const string NAME = 'delete';

    /**
     * @param string $message translation key of the question, given the "count" of the records
     * @param string $hint translation key of what is said under it
     * @param string $icon a Bootstrap Icons name
     * @param string|null $role only somebody with this role is offered the action
     * @param ConfirmInput|null $input a field of the question: a reason of the delete, in RecordDeleting::$input
     */
    public function __construct(
        public string $message = 'crud.confirm_bulk_delete.message',
        public string $hint = 'crud.confirm_delete.hint',
        public string $icon = 'trash',
        public ?string $role = null,
        public string $name = self::NAME,
        public ?ConfirmInput $input = null,
    ) {
    }

    public function handler(): string
    {
        return BulkDeleteActionHandler::class;
    }
}
