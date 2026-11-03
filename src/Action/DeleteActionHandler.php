<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The button of a {@see DeleteAction}, asking first (the "confirm" controller): in the list and its preview drawer the
 * live action "delete" of the list, which then shows the list without the record; on a page of the record the
 * Crud:Delete component, which then goes to the list.
 */
final readonly class DeleteActionHandler implements RecordActionHandler
{
    public function __construct(
        private RecordGuards $guards,
        private TranslatorInterface $translator,
    ) {
    }

    public function button(RecordAction $action, RecordView $record): ?ActionButton
    {
        if (!$action instanceof DeleteAction) {
            throw new InvalidArgumentException(sprintf(
                '%s makes the buttons of a %s.',
                self::class,
                DeleteAction::class,
            ));
        }

        if (!$record->crud->can(CrudOperation::Delete)) {
            return null;
        }
        // Refused for this record: with a reason (ExplainsRefusal) a disabled button saying it, else none.
        $refusal = $this->guards->refusal(CrudOperation::Delete, $record);
        if ($refusal instanceof CrudFailure && !RecordGuards::explains($refusal)) {
            return null;
        }

        $inList = in_array($record->screen, [RecordScreen::List, RecordScreen::Preview], true);

        $button = new ActionButton(
            $action->name,
            'crud.action.delete',
            $action->icon,
            'button',
            $inList ? $this->confirm($action) + [
                'data-action' => 'confirm#ask live#action',
                'data-live-action-param' => 'delete',
                'data-live-id-param' => $record->id,
                'data-live-name-param' => $action->name,
            ] : [],
            $action->inline,
            danger: true,
            component: $inList ? null : 'Crud:Delete',
            props: $inList ? [] : [
                'resource' => $record->crud->name,
                'id' => $record->id,
                'message' => $action->message,
                'hint' => $action->hint,
                'action' => $action->name,
            ],
        );

        return $refusal instanceof CrudFailure
            ? $button->refused($this->translator->trans((string) $refusal->translationKey, $refusal->parameters))
            : $button;
    }

    /**
     * The attributes of the "confirm" controller: the question, translated.
     *
     * @return array<string, string>
     */
    public function confirm(DeleteAction $action): array
    {
        return [
            'type' => 'button',
            'data-controller' => 'confirm',
            'data-confirm-message-value' => $this->translator->trans($action->message),
            'data-confirm-hint-value' => $this->translator->trans($action->hint),
            'data-confirm-accept-value' => $this->translator->trans('crud.action.delete'),
            ...$action->input?->attributes($this->translator) ?? [],
        ];
    }
}
