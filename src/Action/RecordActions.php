<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\FailureMessage;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\RecordChanges;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Security\Actor;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Service\ResetInterface;
use WeakMap;

/**
 * The actions of the records of a resource, for the one asking: the built-in ones (details, edit, delete - for what
 * the resource can do), those of #[AsCrud] and the ways to the pages of #[AsCrudPage] - and their buttons for a record,
 * made by the {@see RecordActionHandler} each names.
 */
final readonly class RecordActions implements ResetInterface
{
    /**
     * What the one asking is offered, by resource, in this request: asked for every row of a list.
     *
     * @var WeakMap<AsCrud, list<RecordAction>>
     */
    private WeakMap $offered;

    public function __construct(
        private CrudRegistry $registry,
        private Actor $actor,
        #[AutowireLocator(RecordActionHandler::TAG)]
        private ContainerInterface $handlers,
        private RecordGuards $guards = new RecordGuards(),
        private ?RecordChanges $changes = null,
        private ?CrudAccess $access = null,
        private ?FailureMessage $failures = null,
    ) {
        $this->offered = new WeakMap();
    }

    /**
     * The next request may be somebody else's.
     */
    public function reset(): void
    {
        $resources = [];
        foreach ($this->offered as $crud => $_) {
            $resources[] = $crud;
        }
        foreach ($resources as $crud) {
            unset($this->offered[$crud]);
        }
    }

    /**
     * What the one asking is offered, in order: details and edit, those of #[AsCrud], the pages, delete. A built-in
     * action declared in #[AsCrud] is where it is declared instead.
     *
     * @return list<RecordAction>
     */
    public function of(AsCrud $crud): array
    {
        if (isset($this->offered[$crud])) {
            return $this->offered[$crud];
        }

        $declared = array_map(static fn (RecordAction $action): string => $action->name, $crud->actions);
        $builtIn = static fn (string $name, CrudOperation $operation): bool
            => !in_array($name, $declared, true) && $crud->can($operation);

        $actions = [];
        if ($builtIn(DetailsAction::NAME, CrudOperation::Read)) {
            $actions[] = new DetailsAction();
        }
        if ($builtIn(EditAction::NAME, CrudOperation::Update)) {
            $actions[] = new EditAction();
        }
        array_push($actions, ...$crud->actions);
        foreach ($this->registry->pagesOf($crud->name) as $page) {
            // A page of the whole resource is no action of a record: the list leads to it. One its page would refuse
            // (CrudAccess: a licence, a tenant) is not offered either.
            if (!$page->collection && $this->pageGranted($page, $crud)) {
                $actions[] = $this->toPage($crud, $page);
            }
        }
        if ($builtIn(DeleteAction::NAME, CrudOperation::Delete)) {
            $actions[] = new DeleteAction();
        }

        $actions = array_values(array_filter(
            $actions,
            fn (RecordAction $action): bool => $action->role === null || $this->actor->isGranted($action->role, $crud),
        ));
        // By priority; of the same one, in the order above (usort keeps it).
        usort(
            $actions,
            static fn (RecordAction $a, RecordAction $b): int => self::priorityOf($a) <=> self::priorityOf($b),
        );

        return $this->offered[$crud] = $actions;
    }

    /**
     * The DeleteAction offered to the one asking under that name - what a live action that deletes goes by, so a
     * delete the buttons do not offer (its role, an action of another kind in its place) is not done either.
     */
    public function deleteAction(AsCrud $crud, string $name = DeleteAction::NAME): ?DeleteAction
    {
        $action = array_find($this->of($crud), static fn (RecordAction $action): bool => $action->name === $name);

        return $action instanceof DeleteAction ? $action : null;
    }

    /**
     * Does the action of that name to a record, on the server (RunAction, or another whose handler RunsRecordAction):
     * only one offered to the one asking (its role) and for this record (its button), what its dialog asks for checked
     * (AsksForInput), in RecordChanges - a transaction, the guards of the action (and of Update, unless the handler
     * says otherwise), RecordChanging and RecordChanged.
     *
     * @throws CrudFailure 403 when it is not offered or is no action done on the server, 422 for what its dialog asks
     *     for, 404 when the record is not there, or what a guard, a listener or the action refused
     */
    public function run(
        AsCrud $crud,
        string $name,
        string $id,
        ?string $input = null,
        RecordScreen $screen = RecordScreen::Details,
    ): ?BulkResult {
        $action = array_find($this->of($crud), static fn (RecordAction $action): bool => $action->name === $name);
        if (!$action instanceof RecordAction) {
            throw CrudFailure::forbidden();
        }
        $handler = $this->handler($action);
        if (!$handler instanceof RunsRecordAction) {
            throw CrudFailure::forbidden();
        }
        $input = $input === null || trim($input) === '' ? null : trim($input);
        if ($action instanceof AsksForInput && $action->input instanceof ConfirmInput) {
            $input = $action->input->accepted($input);
        }
        if (!$this->changes instanceof RecordChanges) {
            throw new LogicException(sprintf(
                'The action "%s" is done through RecordChanges, which is not here.',
                $name,
            ));
        }

        return $this->changes->change(
            $crud,
            $id,
            $action->name,
            static function (RecordView $record) use ($action, $handler, $input): ?BulkResult {
                // Offered for this record: a handler may leave it out for one (its state, a field).
                if (!$handler->button($action, $record) instanceof ActionButton) {
                    throw CrudFailure::forbidden();
                }

                return $handler->run($action, $record, $input);
            },
            $input,
            $handler->updates($action) ? CrudOperation::Update : null,
            $screen,
        );
    }

    /**
     * What the actions need of an item of the list besides its columns.
     *
     * @return list<string>
     */
    public function fields(AsCrud $crud): array
    {
        $fields = [];
        foreach ($this->of($crud) as $action) {
            array_push($fields, ...$action->fields());
        }

        return array_values(array_unique($fields));
    }

    /**
     * The buttons of a record, of the actions offered for it.
     *
     * @return list<ActionButton>
     *
     * @throws LogicException an action names no handler there is
     */
    public function buttons(RecordView $record): array
    {
        $buttons = [];
        foreach ($this->of($record->crud) as $action) {
            // The built-in ones are guarded by their operation (RecordGuard); the others by RecordActionGuard. One
            // refused with a reason (ExplainsRefusal) is shown disabled, saying it; else it goes away.
            $refusal = $this->isBuiltIn($action) ? null : $this->guards->actionRefusal($action->name, $record);
            if ($refusal instanceof CrudFailure && !RecordGuards::explains($refusal)) {
                continue;
            }
            $handler = $this->handler($action);
            // One action, several buttons (MakesButtons): the transitions a record may take now.
            $made = $handler instanceof MakesButtons
                ? $handler->buttons($action, $record)
                : [$handler->button($action, $record)];
            foreach ($made as $button) {
                if ($button instanceof ActionButton) {
                    $button = $button->of($record->crud->name);
                    $buttons[] = $refusal instanceof CrudFailure ? $button->refused($this->reason($refusal)) : $button;
                }
            }
        }

        return $buttons;
    }

    private function reason(CrudFailure $refusal): string
    {
        return $this->failures?->of($refusal) ?? $refusal->getMessage();
    }

    private static function priorityOf(RecordAction $action): int
    {
        return $action instanceof PrioritizedAction ? $action->priority : 0;
    }

    private function isBuiltIn(RecordAction $action): bool
    {
        return $action instanceof DetailsAction || $action instanceof EditAction || $action instanceof DeleteAction;
    }

    private function handler(RecordAction $action): RecordActionHandler
    {
        $id = $action->handler();
        if (!$this->handlers->has($id)) {
            throw new LogicException(sprintf(
                'The action "%s" (%s) is made by %s, which is no service tagged "%s" - is it autoconfigured?',
                $action->name,
                $action::class,
                $id,
                RecordActionHandler::TAG,
            ));
        }

        $handler = $this->handlers->get($id);
        assert($handler instanceof RecordActionHandler);

        return $handler;
    }

    private function pageGranted(AsCrudPage $page, AsCrud $crud): bool
    {
        try {
            $this->access?->assertPageGranted($page, $crud);
        } catch (AccessDeniedHttpException) {
            return false;
        }

        return true;
    }

    private function toPage(AsCrud $crud, AsCrudPage $page): RouteAction
    {
        return new RouteAction($page->name, $crud->route($page->name), $page->icon, $page->role, $page->inline, true);
    }
}
