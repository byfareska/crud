<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Crud;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\ActionPlace;
use Byfareska\Crud\Action\DeleteAction;
use Byfareska\Crud\Action\DeleteActionHandler;
use Byfareska\Crud\Panel\ScopedComponent;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudReturn;
use Byfareska\Crud\Resource\CrudToast;
use Byfareska\Crud\Resource\FailureMessage;
use Byfareska\Crud\Resource\RecordDeletion;
use Byfareska\Crud\Resource\RecordScreen;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Byfareska\Crud\Action\RecordAction;
use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Resource\ConfirmationInput;
use Symfony\UX\LiveComponent\Attribute\LiveArg;

/**
 * The button of a DeleteAction on a page of the record (the details page, the edit form): asks, deletes the record
 * (RecordDeletion) and goes to the list - or says in a toast why it could not.
 */
#[AsLiveComponent('Crud:Delete', template: 'components/Crud/Delete.html.twig')]
final class CrudDelete implements ScopedComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /** Name of the resource, as in #[AsCrud]. */
    #[LiveProp]
    public string $resource = '';

    /** The record, as in its URL. */
    #[LiveProp]
    public string $id = '';

    #[LiveProp]
    public ActionPlace $place = ActionPlace::Bar;

    /** Translation key of the question. */
    #[LiveProp]
    public string $message = 'crud.confirm_delete.message';

    /** Translation key of what is said under it. */
    #[LiveProp]
    public string $hint = 'crud.confirm_delete.hint';

    /** The name of the DeleteAction of the resource it is the button of: its field of the question (input). */
    #[LiveProp]
    public string $action = DeleteAction::NAME;

    /** What crud_action() gives every component of an action; this one draws a button of its own. */
    public ?ActionButton $button = null;

    public function __construct(
        private readonly CrudRegistry $registry,
        private readonly CrudAccess $access,
        private readonly RecordDeletion $deletion,
        private readonly DeleteActionHandler $handler,
        private readonly FailureMessage $failureMessage,
        private readonly CrudReturn $return,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly ?ConfirmationInput $confirmation = null,
        private readonly ?RecordActions $actions = null,
    ) {
    }

    public function panelScope(): ?string
    {
        return $this->resource === '' ? null : $this->registry->get($this->resource)->scope;
    }

    public function getDeleteButton(): ActionButton
    {
        return new ActionButton(
            DeleteAction::NAME,
            'crud.action.delete',
            'trash',
            'button',
            $this->handler->confirm($this->deleteAction()) + [
                'data-action' => 'confirm#ask live#action',
                'data-live-action-param' => 'delete',
            ],
            danger: true,
        );
    }

    /**
     * The DeleteAction declared for the resource under that name (with its input), else one of these texts.
     */
    private function deleteAction(): DeleteAction
    {
        $declared = array_find(
            $this->registry->get($this->resource)->actions,
            fn (RecordAction $action): bool => $action instanceof DeleteAction && $action->name === $this->action,
        );

        return $declared instanceof DeleteAction ? $declared : new DeleteAction($this->message, $this->hint);
    }

    #[LiveAction]
    public function delete(#[LiveArg] ?string $input = null): ?RedirectResponse
    {
        $crud = $this->registry->get($this->resource);
        // The component is reached without its page too (the live endpoint).
        $this->access->assertGranted($crud, CrudOperation::Delete);

        try {
            // Only a delete the buttons offer the one asking (its role, no other action in its place).
            $action = $this->actions instanceof RecordActions
                ? $this->actions->deleteAction($crud, $this->action) ?? throw CrudFailure::forbidden()
                : $this->deleteAction();
            // A reason asked for, checked here: the browser could send none.
            $this->confirmation?->set($action->input?->accepted($input) ?? $input);
            $this->deletion->delete($crud, $this->id, RecordScreen::Details);
        } catch (CrudFailure $failure) {
            $this->dispatchBrowserEvent('toast:show', [
                'title' => $this->translator->trans('crud.toast.delete_failed'),
                'description' => $this->failureMessage->of($failure),
            ]);

            return null;
        }

        $session = $this->requestStack->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            // Translated now: the page that shows it is rendered by another request.
            $session->getFlashBag()->add(
                'toast',
                CrudToast::text($this->translator, $crud, 'deleted', ['id' => $this->id]),
            );
        }

        return new RedirectResponse($this->return->afterDelete($crud, $this->id));
    }
}
