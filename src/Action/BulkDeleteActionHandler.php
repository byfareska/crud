<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\RecordDeletion;
use Byfareska\Crud\Resource\RecordScreen;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The button of a {@see BulkDeleteAction} - asking first - and the deleting, one record after another.
 */
final readonly class BulkDeleteActionHandler implements BulkActionHandler
{
    public function __construct(
        private RecordDeletion $deletion,
        private TranslatorInterface $translator,
    ) {
    }

    public function button(BulkAction $action, BulkSelection $selection): ?ActionButton
    {
        $action = $this->own($action);
        if (!$selection->crud->can(CrudOperation::Delete)) {
            return null;
        }

        return new ActionButton(
            $action->name,
            'crud.action.delete',
            $action->icon,
            attributes: [
                'type' => 'button',
                'data-controller' => 'confirm',
                'data-confirm-message-value' => $this->translator->trans(
                    $action->message,
                    ['count' => $selection->count],
                ),
                'data-confirm-hint-value' => $this->translator->trans($action->hint),
                'data-confirm-accept-value' => $this->translator->trans('crud.action.delete'),
                ...$action->input?->attributes($this->translator) ?? [],
                'data-action' => 'confirm#ask live#action',
                'data-live-action-param' => 'bulk',
                'data-live-name-param' => $action->name,
            ],
            danger: true,
        );
    }

    public function run(BulkAction $action, BulkSelection $selection): BulkResult
    {
        $this->own($action);
        $deleted = 0;
        $failures = [];
        foreach ($selection->ids as $id) {
            try {
                $this->deletion->delete($selection->crud, $id, RecordScreen::List);
                ++$deleted;
            } catch (CrudFailure $failure) {
                $failures[] = $failure;
            }
        }

        $title = $this->translator->trans('crud.toast.bulk_deleted', ['count' => $deleted]);

        return new BulkResult(
            $failures === [] ? $title : $this->translator->trans('crud.toast.delete_failed'),
            $failures === [] ? null : $title . ' · ' . $this->translator->trans(
                'crud.toast.bulk_delete_failed',
                ['count' => count($failures)],
            ),
        );
    }

    private function own(BulkAction $action): BulkDeleteAction
    {
        return $action instanceof BulkDeleteAction ? $action : throw new InvalidArgumentException(sprintf(
            '%s makes the buttons of a %s.',
            self::class,
            BulkDeleteAction::class,
        ));
    }
}
