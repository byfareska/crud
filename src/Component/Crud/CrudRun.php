<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Crud;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\ActionPlace;
use Byfareska\Crud\Action\BulkResult;
use Byfareska\Crud\Action\RecordAction;
use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Action\RunAction;
use Byfareska\Crud\Action\RunActionHandler;
use Byfareska\Crud\Panel\ScopedComponent;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\FailureMessage;
use Byfareska\Crud\Resource\RecordScreen;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The button of a {@see RunAction} on a page of the record (the details, the edit form): asks first when the action
 * has a question, then does it (RecordActions::run()) and opens the page again, to show the record as it is now - or
 * where the action leads (BulkResult::$redirect).
 */
#[AsLiveComponent('Crud:Run', template: 'components/Crud/Run.html.twig')]
final class CrudRun implements ScopedComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /** Name of the resource, as in #[AsCrud]. */
    #[LiveProp]
    public string $resource = '';

    /** The record, as in its URL. */
    #[LiveProp]
    public string $id = '';

    /** The name of the RunAction of the resource. */
    #[LiveProp]
    public string $action = '';

    #[LiveProp]
    public ActionPlace $place = ActionPlace::Bar;

    /** What crud_action() gives every component of an action; this one draws a button of its own. */
    public ?ActionButton $button = null;

    public function __construct(
        private readonly CrudRegistry $registry,
        private readonly CrudAccess $access,
        private readonly RecordActions $actions,
        private readonly RunActionHandler $handler,
        private readonly FailureMessage $failureMessage,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function panelScope(): ?string
    {
        return $this->resource === '' ? null : $this->registry->get($this->resource)->scope;
    }

    public function getRunButton(): ActionButton
    {
        $action = $this->runAction();
        $label = $action->label ?? $this->registry->get($this->resource)->key('action.' . $action->name);

        return new ActionButton(
            $action->name,
            $label,
            $action->icon,
            'button',
            $this->handler->attributes($action, $label) + ['data-live-action-param' => 'run'],
            danger: $action->danger,
            tone: $action->tone,
        );
    }

    #[LiveAction]
    public function run(#[LiveArg] ?string $input = null): ?RedirectResponse
    {
        $crud = $this->registry->get($this->resource);
        // The component is reached without its page too (the live endpoint).
        $this->access->assertGranted($crud);
        // One's own record is nobody else's, whoever signed the id in.
        if ($crud->own && $this->id !== $this->access->ownId()) {
            throw new AccessDeniedHttpException(sprintf('"%s" is the record of the one signed in.', $crud->name));
        }

        try {
            $result = $this->actions->run($crud, $this->action, $this->id, $input, RecordScreen::Details);
        } catch (CrudFailure $failure) {
            $this->dispatchBrowserEvent('toast:show', [
                'title' => $this->translator->trans('crud.toast.action_failed'),
                'description' => $this->failureMessage->of($failure),
            ]);

            return null;
        }

        $session = $this->requestStack->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            // Translated now: the page that shows it is rendered by another request.
            $session->getFlashBag()->add('toast', $result instanceof BulkResult && $result->title !== ''
                ? $result->title
                : $this->translator->trans('crud.toast.done'));
        }

        // The page again, with the record as it is now - or where the action leads.
        $back = $result->redirect ?? $this->requestStack->getMainRequest()?->headers->get('X-Live-Url')
            ?? $this->requestStack->getMainRequest()?->headers->get('referer');

        return is_string($back) && $back !== '' ? new RedirectResponse($back) : null;
    }

    private function runAction(): RunAction
    {
        $crud = $this->registry->get($this->resource);
        $action = array_find(
            $this->actions->of($crud),
            fn (RecordAction $action): bool => $action instanceof RunAction && $action->name === $this->action,
        );

        return $action instanceof RunAction ? $action : throw new NotFoundHttpException(sprintf(
            'The resource "%s" offers no RunAction "%s".',
            $crud->name,
            $this->action,
        ));
    }
}
