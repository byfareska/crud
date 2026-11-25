<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Action;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\AsksForInput;
use Byfareska\Crud\Action\BulkDeleteAction;
use Byfareska\Crud\Action\BulkDeleteActionHandler;
use Byfareska\Crud\Action\ConfirmInput;
use Byfareska\Crud\Action\DeleteAction;
use Byfareska\Crud\Action\NoAction;
use Byfareska\Crud\Action\RecordAction;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\DefaultRecordDeletion;
use Byfareska\Crud\Resource\RecordActionGuard;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Event\BulkActionDone;
use Byfareska\Crud\Event\BulkActionRunning;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Action\BulkAction;
use Byfareska\Crud\Action\BulkActions;
use Byfareska\Crud\Action\BulkSelection;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\DeletedRecords;
use Byfareska\Crud\Tests\Unit\Fixtures\HiddenAction;
use Byfareska\Crud\Tests\Unit\Fixtures\TaggingBulkHandler;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The bulk actions of a resource: offered by role, their buttons and their running by the handler each names.
 */
final class BulkActionsTest extends TestCase
{
    public function testTheActionsOfTheOneAskingAndTheirButtons(): void
    {
        $crud = $this->thing([$this->action('archive'), $this->action('purge', 'ROLE_ADMIN')]);
        $selection = new BulkSelection($crud, ['7', '8']);

        self::assertSame(['archive'], array_map(
            static fn (BulkAction $action): string => $action->name,
            $this->actions([])->of($crud),
        ));
        self::assertSame(['archive', 'purge'], array_map(
            static fn (ActionButton $button): string => $button->name,
            $this->actions(['ROLE_ADMIN'])->buttons($selection),
        ));
    }

    public function testOnlyAnActionOfferedForTheRecordsIsRun(): void
    {
        $crud = $this->thing([$this->action('archive'), $this->action('purge', 'ROLE_ADMIN')]);
        $selection = new BulkSelection($crud, ['7', '8']);
        $actions = $this->actions([]);

        self::assertSame('archive: 7, 8', $actions->run('archive', $selection)?->title);
        self::assertNull($actions->run('purge', $selection), 'Not for the one asking.');
        self::assertNull($actions->run('nothing', $selection));
        self::assertNull(
            $actions->run('archive', new BulkSelection($crud, ['13'])),
            'Its handler does not offer it for these records.',
        );
    }

    public function testWhatItsDialogAsksForIsCheckedBeforeItIsRun(): void
    {
        $action = new readonly class implements BulkAction, AsksForInput {
            public string $name;

            public ?string $role;

            public ?ConfirmInput $input;

            public function __construct()
            {
                $this->name = 'status';
                $this->role = null;
                $this->input = new ConfirmInput('app.status', choices: ['app.status.done' => 'done']);
            }

            public function handler(): string
            {
                return TaggingBulkHandler::class;
            }
        };
        $crud = $this->thing([$action]);
        $actions = $this->actions([]);

        self::assertSame('status: 7', $actions->run('status', new BulkSelection($crud, ['7'], input: 'done'))?->title);

        $this->expectException(CrudFailure::class);

        $actions->run('status', new BulkSelection($crud, ['7'], input: 'paid'));
    }

    public function testTheListenersHearWhatIsDoneAndMayRefuse(): void
    {
        $crud = $this->thing([$this->action('archive'), $this->action('purge')]);
        $heard = [];
        $events = new EventDispatcher();
        $events->addListener(BulkActionRunning::class, static function (BulkActionRunning $event) use (&$heard): void {
            $heard[] = 'running ' . $event->action->name;
            if ($event->action->name === 'purge') {
                throw new CrudFailure(409, 'Not on a Friday.');
            }
        });
        $events->addListener(BulkActionDone::class, static function (BulkActionDone $event) use (&$heard): void {
            $heard[] = 'done ' . $event->action->name . ': ' . $event->result?->title;
        });
        $actions = new BulkActions($this->actor([]), new ServiceLocator([
            TaggingBulkHandler::class => static fn (): TaggingBulkHandler => new TaggingBulkHandler(),
        ]), $events);

        $actions->run('archive', new BulkSelection($crud, ['7']));
        try {
            $actions->run('purge', new BulkSelection($crud, ['7']));
            self::fail('A listener refused.');
        } catch (CrudFailure) {
        }

        self::assertSame(['running archive', 'done archive: archive: 7', 'running purge'], $heard);
    }

    public function testAnActionWhoseHandlerIsNoServiceIsAMistake(): void
    {
        $crud = $this->thing([$this->action('archive')]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('archive');

        new BulkActions($this->actor([]), new ServiceLocator([]))->buttons(new BulkSelection($crud, ['7']));
    }

    public function testABulkDeleteWithoutARoleTakesThatOfTheDeleteAction(): void
    {
        $gadget = $this->gadget([new DeleteAction(role: 'ROLE_ADMIN')], [new BulkDeleteAction()]);
        $records = new DeletedRecords();
        $selection = new BulkSelection($gadget, ['7', '8']);

        $staff = $this->deleting($records, []);
        self::assertSame([], $staff->of($gadget));
        self::assertSame([], $staff->buttons($selection), 'No button for who may not delete one record.');
        self::assertNull($staff->run(BulkDeleteAction::NAME, $selection), 'Nor done when asked anyway.');
        self::assertSame([], $records->deleted);

        $admin = $this->deleting($records, ['ROLE_ADMIN']);
        self::assertSame([BulkDeleteAction::NAME], array_column($admin->buttons($selection), 'name'));
        self::assertSame('crud.toast.bulk_deleted', $admin->run(BulkDeleteAction::NAME, $selection)?->title);
        self::assertSame(['7', '8'], $records->deleted);
    }

    public function testTheRoleOfItsOwnWins(): void
    {
        $gadget = $this->gadget(
            [new DeleteAction(role: 'ROLE_ADMIN')],
            [new BulkDeleteAction(role: 'ROLE_CLEANER')],
        );
        $records = new DeletedRecords();
        $selection = new BulkSelection($gadget, ['7']);

        self::assertSame([], $this->deleting($records, ['ROLE_ADMIN'])->buttons($selection));
        self::assertNull($this->deleting($records, ['ROLE_ADMIN'])->run(BulkDeleteAction::NAME, $selection));
        self::assertSame([], $records->deleted);

        $this->deleting($records, ['ROLE_CLEANER'])->run(BulkDeleteAction::NAME, $selection);
        self::assertSame(['7'], $records->deleted);
    }

    /**
     * @return iterable<string, array{list<RecordAction>}>
     */
    public static function noRoleToTake(): iterable
    {
        yield 'the built-in delete' => [[]];
        yield 'a NoAction in its place: deleting in bulk only' => [[new NoAction(DeleteAction::NAME)]];
        yield 'an action of another kind in its place' => [[new HiddenAction(DeleteAction::NAME, 'ROLE_ADMIN')]];
        yield 'a DeleteAction without a role' => [[new DeleteAction(message: 'app.gadget.delete')]];
        yield 'one of them without a role' => [[
            new DeleteAction(role: 'ROLE_ADMIN'),
            new DeleteAction(role: 'ROLE_ARCHIVIST', name: 'archive'),
            new DeleteAction(name: 'purge'),
        ]];
        yield 'the built-in besides one of another name' => [[new DeleteAction(role: 'ROLE_ADMIN', name: 'purge')]];
    }

    /**
     * @param list<RecordAction> $actions
     */
    #[DataProvider('noRoleToTake')]
    public function testWithoutADeleteActionOfARoleItIsForEverybody(array $actions): void
    {
        $gadget = $this->gadget($actions, [new BulkDeleteAction()]);
        $records = new DeletedRecords();
        $selection = new BulkSelection($gadget, ['7']);
        $deleting = $this->deleting($records, []);

        self::assertSame([BulkDeleteAction::NAME], array_column($deleting->buttons($selection), 'name'));
        $deleting->run(BulkDeleteAction::NAME, $selection);
        self::assertSame(['7'], $records->deleted);
    }

    public function testOneOfTheDeleteActionsOfTheOneAskingIsEnough(): void
    {
        $gadget = $this->gadget(
            [new DeleteAction(role: 'ROLE_ADMIN'), new DeleteAction(role: 'ROLE_ARCHIVIST', name: 'archive')],
            [new BulkDeleteAction()],
        );
        $selection = new BulkSelection($gadget, ['7']);

        self::assertCount(1, $this->deleting(new DeletedRecords(), ['ROLE_ARCHIVIST'])->buttons($selection));
        self::assertSame([], $this->deleting(new DeletedRecords(), ['ROLE_EDITOR'])->buttons($selection));
    }

    public function testARecordAGuardOfTheDeleteActionRefusesStaysAndTheOthersGo(): void
    {
        $paid = new readonly class implements RecordActionGuard {
            public function resource(): string
            {
                return 'gadget';
            }

            public function fields(): array
            {
                return ['paid'];
            }

            public function allowsAction(string $action, RecordView $record): bool
            {
                return $action !== DeleteAction::NAME || ($record->item['paid'] ?? false) !== true;
            }
        };
        $gadget = $this->gadget([], [new BulkDeleteAction()]);
        $records = new DeletedRecords(['8' => ['paid' => true]]);

        $result = $this->deleting($records, [], [$paid])
            ->run(BulkDeleteAction::NAME, new BulkSelection($gadget, ['7', '8', '9']));

        self::assertSame(['7', '9'], $records->deleted);
        self::assertSame('crud.toast.delete_failed', $result?->title, 'Told that one could not be.');
        self::assertSame('crud.toast.bulk_deleted · crud.toast.bulk_delete_failed', $result->description);

        // As it is not deleted on its own, nor by a DeleteAction named otherwise.
        $deletion = new DefaultRecordDeletion($records, new RecordGuards(actionGuards: [$paid]));
        try {
            $deletion->delete($this->gadget([new DeleteAction(name: 'remove')], []), '8', RecordScreen::List);
            self::fail('Deleted.');
        } catch (CrudFailure $failure) {
            self::assertSame(403, $failure->status);
        }
        self::assertSame(['7', '9'], $records->deleted);
    }

    /**
     * @param list<RecordAction> $actions
     * @param list<BulkAction> $bulkActions
     */
    private function gadget(array $actions, array $bulkActions): AsCrud
    {
        return new AsCrud(
            'gadget',
            '/gadgets',
            'box',
            source: new DoctrineSource(stdClass::class),
            actions: $actions,
            bulkActions: $bulkActions,
        );
    }

    /**
     * The bulk actions as the bundle does them: BulkDeleteActionHandler deleting through DefaultRecordDeletion.
     *
     * @param list<string> $roles
     * @param list<RecordActionGuard> $actionGuards
     */
    private function deleting(CrudRecords $records, array $roles, array $actionGuards = []): BulkActions
    {
        $deletion = new DefaultRecordDeletion($records, new RecordGuards(actionGuards: $actionGuards));

        return new BulkActions($this->actor($roles), new ServiceLocator([
            BulkDeleteActionHandler::class => static fn (): BulkDeleteActionHandler
                => new BulkDeleteActionHandler($deletion, new IdentityTranslator()),
        ]));
    }

    /**
     * @param list<BulkAction> $actions
     */
    private function thing(array $actions): AsCrud
    {
        return new AsCrud('thing', '/things', 'box', list: ListThingsDto::class, bulkActions: $actions);
    }

    private function action(string $name, ?string $role = null): BulkAction
    {
        return new readonly class ($name, $role) implements BulkAction {
            public function __construct(
                public string $name,
                public ?string $role,
            ) {
            }

            public function handler(): string
            {
                return TaggingBulkHandler::class;
            }
        };
    }

    /**
     * @param list<string> $roles
     */
    private function actions(array $roles): BulkActions
    {
        return new BulkActions(
            $this->actor($roles),
            new ServiceLocator([
                TaggingBulkHandler::class => static fn (): TaggingBulkHandler => new TaggingBulkHandler(),
            ]),
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
