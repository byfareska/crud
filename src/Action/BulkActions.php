<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Event\BulkActionDone;
use Byfareska\Crud\Event\BulkActionRunning;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Security\Actor;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The bulk actions of a resource for the one asking - those of #[AsCrud(bulkActions: ...)] - their buttons for the
 * records chosen and their running, by the {@see BulkActionHandler} each names.
 */
final readonly class BulkActions
{
    public function __construct(
        private Actor $actor,
        #[AutowireLocator(BulkActionHandler::TAG)]
        private ContainerInterface $handlers,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @return list<BulkAction>
     */
    public function of(AsCrud $crud): array
    {
        return array_values(array_filter(
            $crud->bulkActions,
            fn (BulkAction $action): bool => $action->role === null || $this->actor->isGranted($action->role, $crud),
        ));
    }

    /**
     * The buttons of the actions offered for the records chosen.
     *
     * @return list<ActionButton>
     *
     * @throws LogicException an action names no handler there is
     */
    public function buttons(BulkSelection $selection): array
    {
        $buttons = [];
        foreach ($this->of($selection->crud) as $action) {
            $button = $this->handler($action)->button($action, $selection);
            if ($button instanceof ActionButton) {
                $buttons[] = $button;
            }
        }

        return $buttons;
    }

    /**
     * Does the action of that name with the records chosen - if it is offered for them; null when not, or when it has
     * nothing to tell.
     *
     * @throws LogicException an action names no handler there is
     * @throws CrudFailure 422 what its dialog asks for (AsksForInput) is missing or none of its choices; what a
     *     listener of BulkActionRunning refused
     */
    public function run(string $name, BulkSelection $selection): ?BulkResult
    {
        foreach ($this->of($selection->crud) as $action) {
            if ($action->name !== $name) {
                continue;
            }
            $handler = $this->handler($action);
            if (!$handler->button($action, $selection) instanceof ActionButton) {
                return null;
            }
            if ($action instanceof AsksForInput) {
                $action->input?->accepted($selection->input);
            }
            // A listener may refuse (CrudFailure); an audit log hears what was done to which records.
            $this->events?->dispatch(new BulkActionRunning($action, $selection));
            $result = $handler->run($action, $selection);
            $this->events?->dispatch(new BulkActionDone($action, $selection, $result));

            return $result;
        }

        return null;
    }

    private function handler(BulkAction $action): BulkActionHandler
    {
        $id = $action->handler();
        if (!$this->handlers->has($id)) {
            throw new LogicException(sprintf(
                'The bulk action "%s" (%s) is done by %s, which is no service tagged "%s" - is it autoconfigured?',
                $action->name,
                $action::class,
                $id,
                BulkActionHandler::TAG,
            ));
        }

        $handler = $this->handlers->get($id);
        assert($handler instanceof BulkActionHandler);

        return $handler;
    }
}
