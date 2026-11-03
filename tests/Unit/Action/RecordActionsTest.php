<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Action;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\ComponentAction;
use Byfareska\Crud\Action\ComponentActionHandler;
use Byfareska\Crud\Action\DeleteAction;
use Byfareska\Crud\Action\DeleteActionHandler;
use Byfareska\Crud\Action\DetailsActionHandler;
use Byfareska\Crud\Action\EditAction;
use Byfareska\Crud\Action\EditActionHandler;
use Byfareska\Crud\Action\MakesButtons;
use Byfareska\Crud\Action\NoAction;
use Byfareska\Crud\Action\NoActionHandler;
use Byfareska\Crud\Action\RecordAction;
use Byfareska\Crud\Action\RecordActionHandler;
use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Action\RouteAction;
use Byfareska\Crud\Action\RouteActionHandler;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\ExplainsRefusal;
use Byfareska\Crud\Resource\RecordActionGuard;
use Byfareska\Crud\Resource\RecordGuard;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\HiddenAction;
use Byfareska\Crud\Tests\Unit\Fixtures\HiddenActionHandler;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Byfareska\Crud\Tests\Unit\Fixtures\RecordingActor;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingHistoryPage;
use Byfareska\Crud\UseCase\ListPage;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The actions of a record: built-in, declared, pages, and their buttons made by the handler each names.
 */
final class RecordActionsTest extends TestCase
{
    public function testThePagesOfARecordAreOfferedLikeItsActions(): void
    {
        $thing = $this->thing();

        self::assertSame(['login-as'], $this->names($this->actions([])->of($thing)));

        [, $history] = $this->actions(['ROLE_AUDITOR'])->of($thing);
        self::assertInstanceOf(RouteAction::class, $history);
        self::assertSame(['history', 'crud_thing_history', true], [$history->name, $history->route, $history->page]);
        self::assertSame(['id' => '7'], $history->parameters($thing, '7'));
    }

    public function testAPageItsAccessRefusesIsNotOffered(): void
    {
        $unlicensed = new class implements CrudAccess {
            public function isGranted(AsCrud $crud, ?CrudOperation $operation = null): bool
            {
                return true;
            }

            public function assertGranted(AsCrud $crud, ?CrudOperation $operation = null): void
            {
            }

            public function assertPageGranted(AsCrudPage $page, AsCrud $crud): void
            {
                // The history is a module of its own, not in the licence.
                if ($page->name === 'history') {
                    throw new AccessDeniedHttpException('Not in the licence.');
                }
            }

            public function ownId(): string
            {
                return '1';
            }
        };
        $actions = $this->actions(['ROLE_AUDITOR']);
        $refusing = new RecordActions(
            new CrudRegistry(pages: [ThingHistoryPage::class]),
            $this->actor(['ROLE_AUDITOR']),
            new ServiceLocator([]),
            access: $unlicensed,
        );

        self::assertContains('history', $this->names($actions->of($this->thing())));
        self::assertNotContains('history', $this->names($refusing->of($this->thing())));
    }

    public function testTheBuiltInActionsAreThereForWhatTheResourceCanDo(): void
    {
        $gadget = $this->gadget([new RouteAction('login-as', 'app_login_as', 'key')]);
        self::assertSame(['details', 'edit', 'login-as', 'delete'], $this->names($this->actions([])->of($gadget)));

        // One declared takes the place of the built-in one.
        $edit = new EditAction(inline: true);
        $gadget = $this->gadget([new RouteAction('login-as', 'app_login_as', 'key'), $edit]);
        $actions = $this->actions([])->of($gadget);
        self::assertSame(['details', 'login-as', 'edit', 'delete'], $this->names($actions));
        self::assertSame($edit, $actions[2]);

        $readOnly = new AsCrud('gadget', '/gadgets', 'box', source: new DoctrineSource(
            stdClass::class,
            operations: [CrudOperation::List, CrudOperation::Read],
        ));
        self::assertSame(['details'], $this->names($this->actions([])->of($readOnly)));
    }

    public function testAPriorityPlacesAnActionAndNoActionTakesABuiltInOneAway(): void
    {
        $gadget = $this->gadget([
            new RouteAction('login-as', 'app_login_as', 'key'),
            new RouteAction('first', 'app_login_as', 'star', priority: -10),
            new NoAction('details'),
        ]);
        $actions = $this->actions([]);

        self::assertSame(['first', 'edit', 'login-as', 'details', 'delete'], $this->names($actions->of($gadget)));
        $buttons = $actions->buttons(new RecordView($gadget, '7', [], RecordScreen::Details));
        self::assertSame(['first', 'edit', 'login-as', 'delete'], array_column($buttons, 'name'));
    }

    public function testARefusalWithAReasonIsADisabledButtonSayingIt(): void
    {
        $paid = new readonly class implements RecordGuard, ExplainsRefusal {
            public function resource(): string
            {
                return 'gadget';
            }

            public function fields(): array
            {
                return [];
            }

            public function allows(CrudOperation $operation, RecordView $record): bool
            {
                return $operation !== CrudOperation::Delete;
            }

            public function refusal(CrudOperation|string $what, RecordView $record): CrudFailure
            {
                return new CrudFailure(409, 'Paid.', translationKey: 'app.invoice.paid');
            }
        };
        $closed = new readonly class implements RecordActionGuard, ExplainsRefusal {
            public function resource(): string
            {
                return 'gadget';
            }

            public function fields(): array
            {
                return [];
            }

            public function allowsAction(string $action, RecordView $record): bool
            {
                return $action !== 'login-as';
            }

            public function refusal(CrudOperation|string $what, RecordView $record): CrudFailure
            {
                return new CrudFailure(409, 'Paid.', translationKey: 'app.invoice.paid');
            }
        };
        $gadget = $this->gadget([new RouteAction('login-as', 'app_login_as', 'key')]);
        $buttons = array_column(
            $this->actions([], guards: [$paid], actionGuards: [$closed])->buttons(
                new RecordView($gadget, '7', [], RecordScreen::List),
            ),
            null,
            'name',
        );

        // The reason as the guard said it: translated (an identity translator here), or its text.
        $reasons = ['delete' => 'app.invoice.paid', 'login-as' => 'Paid.'];
        foreach ($reasons as $name => $reason) {
            self::assertTrue($buttons[$name]->attributes['disabled'] ?? false, $name . ' is there, disabled.');
            self::assertSame($reason, $buttons[$name]->attributes['title'] ?? null);
            self::assertArrayNotHasKey('href', $buttons[$name]->attributes, 'Nothing it would do is left.');
            self::assertArrayNotHasKey('data-live-action-param', $buttons[$name]->attributes);
        }

        // And what is done anyway is refused with it.
        try {
            $record = new RecordView($gadget, '7', [], RecordScreen::List);
            new RecordGuards([$paid])->assertAllowed(CrudOperation::Delete, $record);
            self::fail('Deleted.');
        } catch (CrudFailure $failure) {
            self::assertSame([409, 'app.invoice.paid'], [$failure->status, $failure->translationKey]);
        }
    }

    public function testAnActionOfSeveralButtons(): void
    {
        $handler = new readonly class implements RecordActionHandler, MakesButtons {
            public function button(RecordAction $action, RecordView $record): ?ActionButton
            {
                return null;
            }

            public function buttons(RecordAction $action, RecordView $record): iterable
            {
                // The transitions of a draft.
                foreach (['send', 'reject'] as $transition) {
                    yield new ActionButton($transition, 'app.order.' . $transition, 'arrow-right');
                }
            }
        };
        $actions = new RecordActions(
            new CrudRegistry(),
            $this->actor([]),
            new ServiceLocator([HiddenActionHandler::class => static fn (): RecordActionHandler => $handler]),
        );
        $thing = $this->thing([new HiddenAction('transitions')]);

        $buttons = $actions->buttons(new RecordView($thing, '7', [], RecordScreen::Details));

        self::assertSame(['send', 'reject'], array_column($buttons, 'name'));
    }

    public function testADeleteIsOnlyTheOneOffered(): void
    {
        $everybody = $this->gadget();
        self::assertInstanceOf(DeleteAction::class, $this->actions([])->deleteAction($everybody));
        self::assertNull($this->actions([])->deleteAction($everybody, 'purge'), 'No action of that name.');

        // A role of the action: the live action that deletes goes by it too, not only the button.
        $admins = $this->gadget([new DeleteAction(role: 'ROLE_ADMIN')]);
        self::assertNull($this->actions([])->deleteAction($admins));
        self::assertInstanceOf(DeleteAction::class, $this->actions(['ROLE_ADMIN'])->deleteAction($admins));

        // Another action in its place.
        self::assertNull($this->actions(['ROLE_ADMIN'])->deleteAction($this->gadget([new NoAction('delete')])));
    }

    public function testTheBuiltInActionsFitTheScreen(): void
    {
        $gadget = $this->gadget();

        $row = $this->buttons($gadget, RecordScreen::List);
        self::assertSame(['details', 'edit', 'delete'], array_keys($row));
        self::assertSame(['/gadgets/7', true], [$row['details']->attributes['href'], $row['details']->inline]);
        self::assertSame('delete', $row['delete']->attributes['data-live-action-param']);
        self::assertTrue($row['delete']->danger);

        // The details page: no way to itself, editing is the main thing, deleting is a component of its own.
        $page = $this->buttons($gadget, RecordScreen::Details);
        self::assertSame(['edit', 'delete'], array_keys($page));
        self::assertTrue($page['edit']->primary);
        self::assertSame('Crud:Delete', $page['delete']->component);
        self::assertSame(['resource' => 'gadget', 'id' => '7'], array_slice($page['delete']->props, 0, 2));

        self::assertSame(['details', 'delete'], array_keys($this->buttons($gadget, RecordScreen::Form)));
    }

    public function testWhatItsGuardsRefuseIsNotOffered(): void
    {
        $paid = new readonly class implements RecordGuard {
            public function resource(): string
            {
                return 'gadget';
            }

            public function fields(): array
            {
                return ['paid'];
            }

            public function allows(CrudOperation $operation, RecordView $record): bool
            {
                return $operation === CrudOperation::Read || ($record->item['paid'] ?? false) !== true;
            }
        };
        $gadget = $this->gadget();

        self::assertSame(
            ['details'],
            array_keys($this->buttons($gadget, RecordScreen::List, ['paid' => true], [$paid])),
        );
        self::assertSame(
            ['details', 'edit', 'delete'],
            array_keys($this->buttons($gadget, RecordScreen::List, ['paid' => false], [$paid])),
        );
        self::assertSame(['paid'], new RecordGuards([$paid])->fields($gadget));
        self::assertSame([], new RecordGuards([$paid])->fields($this->thing()));
    }

    public function testWhatAGuardOfItsActionsRefusesIsNotOffered(): void
    {
        $guard = new readonly class implements RecordActionGuard {
            public function resource(): string
            {
                return 'gadget';
            }

            public function fields(): array
            {
                return ['locked'];
            }

            public function allowsAction(string $action, RecordView $record): bool
            {
                return $action !== 'login-as' || ($record->item['locked'] ?? false) !== true;
            }
        };
        $gadget = $this->gadget([new RouteAction('login-as', 'app_login_as', 'key')]);
        $actions = $this->actions([], actionGuards: [$guard]);

        $locked = $actions->buttons(new RecordView($gadget, '7', ['locked' => true], RecordScreen::List));
        $open = $actions->buttons(new RecordView($gadget, '7', ['locked' => false], RecordScreen::List));
        self::assertSame(['details', 'edit', 'delete'], array_column($locked, 'name'), 'The built-in: by operation.');
        self::assertSame(['details', 'edit', 'login-as', 'delete'], array_column($open, 'name'));
        self::assertSame(['locked'], new RecordGuards(actionGuards: [$guard])->fields($gadget));
    }

    public function testEachActionIsMadeByTheHandlerItNames(): void
    {
        $thing = $this->thing([
            new RouteAction('login-as', 'app_login_as', 'key', inline: true),
            new HiddenAction('hidden'),
            new ComponentAction('restock', 'App:Restock', 'box', props: ['step' => 10]),
        ]);
        $record = new RecordView($thing, '7', ['name' => 'Mug'], RecordScreen::List);

        $buttons = $this->actions([])->buttons($record);

        self::assertCount(2, $buttons, 'An action its handler does not offer for the record is left out.');
        [$login, $restock] = $buttons;
        self::assertSame(
            ['login-as', 'crud.thing.action.login-as', 'key', 'a', true, null],
            [$login->name, $login->label, $login->icon, $login->tag, $login->inline, $login->component],
        );
        self::assertSame(['href' => '/login-as/7', 'data-turbo' => 'false'], $login->attributes);

        self::assertSame(['App:Restock', 'crud.thing.action.restock'], [$restock->component, $restock->label]);
        self::assertSame(['record' => $record, 'step' => 10], $restock->props);

        // More parameters, a text of its own, a new tab, a visit by Turbo.
        $report = $this->thing([new RouteAction(
            'report',
            'app_login_as',
            'file-pdf',
            parameters: ['format' => 'pdf'],
            label: 'app.report',
            target: '_blank',
            turbo: true,
        )]);
        [$button] = $this->actions([])->buttons(new RecordView($report, '7', [], RecordScreen::Details));
        self::assertSame('app.report', $button->label);
        self::assertSame(
            ['href' => '/login-as/7?format=pdf', 'target' => '_blank', 'rel' => 'noopener'],
            $button->attributes,
        );
    }

    public function testAnActionInTheListSeesThePageOfTheList(): void
    {
        $thing = $this->thing([new ComponentAction('restock', 'App:Restock', 'box')]);
        $list = new ListPage([['id' => '7', 'name' => 'Mug'], ['id' => '8', 'name' => 'Cup']], 30, 2, 2);

        [$restock] = $this->actions([])->buttons(
            new RecordView($thing, '7', $list->items[0], RecordScreen::List, $list),
        );

        $record = $restock->props['record'] ?? null;
        self::assertInstanceOf(RecordView::class, $record);
        self::assertSame($list, $record->list);
    }

    public function testTheListAsksForWhatTheActionsNeed(): void
    {
        $thing = $this->thing([
            new HiddenAction('hidden'),
            new ComponentAction('restock', 'App:Restock', 'box', fields: ['stock', 'secret']),
            new HiddenAction('only-for-auditors', 'ROLE_AUDITOR'),
        ]);

        self::assertSame(['secret', 'id', 'stock'], $this->actions([])->fields($thing));
    }

    public function testAnActionWhoseHandlerIsNoServiceIsAMistake(): void
    {
        $thing = $this->thing([new HiddenAction('lost', handler: ComponentActionHandler::class)]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ComponentActionHandler::class);

        $this->actions([], withComponents: false)->buttons(new RecordView($thing, '7', [], RecordScreen::List));
    }

    /**
     * @param list<RecordAction> $actions
     */
    private function thing(array $actions = [new RouteAction('login-as', 'app_login_as', 'key')]): AsCrud
    {
        return new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, actions: $actions);
    }

    /**
     * A resource that can do everything.
     *
     * @param list<RecordAction> $actions
     */
    private function gadget(array $actions = []): AsCrud
    {
        return new AsCrud('gadget', '/gadgets', 'box', source: new DoctrineSource(stdClass::class), actions: $actions);
    }

    /**
     * The buttons of the record 7, by name.
     *
     * @param array<string, mixed> $item
     * @param list<RecordGuard> $guards
     *
     * @return array<string, ActionButton>
     */
    private function buttons(AsCrud $crud, RecordScreen $screen, array $item = [], array $guards = []): array
    {
        $buttons = $this->actions([], guards: $guards)->buttons(new RecordView($crud, '7', $item, $screen));

        return array_column($buttons, null, 'name');
    }

    /**
     * @param list<RecordAction> $actions
     *
     * @return list<string>
     */
    private function names(array $actions): array
    {
        return array_map(static fn (RecordAction $action): string => $action->name, $actions);
    }

    public function testWhatIsOfferedIsAskedOnceARequest(): void
    {
        $actor = new RecordingActor(['ROLE_ADMIN']);
        $actions = new RecordActions(new CrudRegistry(), $actor, new ServiceLocator([]));
        $crud = $this->thing([new RouteAction('login-as', 'app_login_as', 'key', role: 'ROLE_ADMIN')]);

        $offered = $actions->of($crud);
        self::assertSame($offered, $actions->of($crud), 'Every row of the list asks: the roles are asked once.');
        self::assertCount(1, $actor->asked);

        // The next request may be somebody else's.
        $actions->reset();
        $actions->of($crud);
        self::assertCount(2, $actor->asked);
    }

    /**
     * @param list<string> $roles
     * @param list<RecordGuard> $guards
     * @param list<RecordActionGuard> $actionGuards
     */
    private function actions(
        array $roles,
        bool $withComponents = true,
        array $guards = [],
        array $actionGuards = [],
    ): RecordActions {
        $routes = new RouteCollection();
        $routes->add('app_login_as', new Route('/login-as/{id}'));
        $routes->add('crud_thing_history', new Route('/things/{id}/history'));
        $routes->add('crud_gadget_show', new Route('/gadgets/{id}'));
        $routes->add('crud_gadget_edit', new Route('/gadgets/{id}/edit'));
        $urls = new UrlGenerator($routes, new RequestContext());
        $recordGuards = new RecordGuards($guards, $actionGuards);
        $requests = new RequestStack();

        $handlers = [
            RouteActionHandler::class => static fn (): RouteActionHandler => new RouteActionHandler($urls),
            HiddenActionHandler::class => static fn (): HiddenActionHandler => new HiddenActionHandler(),
            NoActionHandler::class => static fn (): NoActionHandler => new NoActionHandler(),
            DetailsActionHandler::class => static fn (): DetailsActionHandler
                => new DetailsActionHandler($urls, $recordGuards, $requests),
            EditActionHandler::class => static fn (): EditActionHandler
                => new EditActionHandler($urls, $recordGuards, $requests),
            DeleteActionHandler::class => static fn (): DeleteActionHandler
                => new DeleteActionHandler($recordGuards, new IdentityTranslator()),
        ];
        if ($withComponents) {
            $handlers[ComponentActionHandler::class] = static fn (): ComponentActionHandler
                => new ComponentActionHandler();
        }

        return new RecordActions(
            new CrudRegistry(pages: [ThingHistoryPage::class]),
            $this->actor($roles),
            new ServiceLocator($handlers),
            $recordGuards,
        );
    }

    /** @param list<string> $roles */
    private function actor(array $roles): Actor
    {
        return new readonly class ($roles) implements Actor {
            /** @param list<string> $roles */
            public function __construct(private array $roles)
            {
            }

            public function id(): string
            {
                return '1';
            }

            public function isGranted(string $attribute, mixed $subject = null): bool
            {
                return in_array($attribute, $this->roles, true);
            }
        };
    }
}
