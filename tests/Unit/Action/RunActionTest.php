<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Action;

use Byfareska\Crud\Action\ConfirmInput;
use Byfareska\Crud\Action\RecordAction;
use Byfareska\Crud\Action\RecordActionRunner;
use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Action\RouteAction;
use Byfareska\Crud\Action\RouteActionHandler;
use Byfareska\Crud\Action\RunAction;
use Byfareska\Crud\Action\RunActionHandler;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Event\RecordChanged;
use Byfareska\Crud\Event\RecordChanging;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\RecordActionGuard;
use Byfareska\Crud\Resource\RecordChanges;
use Byfareska\Crud\Resource\RecordGuard;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\ApproveInvoice;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * An action done to one record on the server: offered, its question checked, guarded, heard by the listeners.
 */
final class RunActionTest extends TestCase
{
    /** @var list<string> */
    private array $heard = [];

    private ApproveInvoice $runner;

    protected function setUp(): void
    {
        $this->runner = new ApproveInvoice();
    }

    public function testItIsDoneThroughTheGuardsAndTheListeners(): void
    {
        $crud = $this->invoices([new RunAction('approve', 'check2', ApproveInvoice::class, input: new ConfirmInput(
            'app.comment',
            required: false,
        ))]);

        $result = $this->actions()->run($crud, 'approve', '7', ' looks fine ');

        self::assertSame('approved 7', $result?->title);
        self::assertSame([['7', 'looks fine']], $this->runner->done);
        self::assertSame(['changing approve 7', 'changed approve 7'], $this->heard);
    }

    public function testOnlyWhatIsOfferedIsDone(): void
    {
        $crud = $this->invoices([
            new RunAction('approve', 'check2', ApproveInvoice::class, role: 'ROLE_ACCOUNTANT'),
            new RouteAction('print', 'app_print', 'printer'),
        ]);

        $refused = ['approve' => 'its role', 'print' => 'a link, not done on the server', 'nothing' => 'none'];
        foreach ($refused as $name => $why) {
            try {
                $this->actions()->run($crud, $name, '7');
                self::fail('Done: ' . $why);
            } catch (CrudFailure $failure) {
                self::assertSame(403, $failure->status, $why);
            }
        }
        self::assertSame([], $this->runner->done);
        self::assertSame('approved 7', $this->actions(['ROLE_ACCOUNTANT'])->run($crud, 'approve', '7')?->title);
    }

    public function testWhatItsDialogAsksForIsChecked(): void
    {
        $crud = $this->invoices([
            new RunAction('approve', 'check2', ApproveInvoice::class, input: new ConfirmInput('app.comment')),
        ]);

        $this->expectException(CrudFailure::class);

        try {
            $this->actions()->run($crud, 'approve', '7', '  ');
        } finally {
            self::assertSame([], $this->runner->done);
            self::assertSame([], $this->heard);
        }
    }

    public function testTheGuardsOfTheActionAndOfUpdateAreAsked(): void
    {
        $paid = new readonly class implements RecordActionGuard {
            public function resource(): string
            {
                return 'invoice';
            }

            public function fields(): array
            {
                return [];
            }

            public function allowsAction(string $action, RecordView $record): bool
            {
                return $action !== 'approve' || $record->id !== '8';
            }
        };
        $locked = new readonly class implements RecordGuard {
            public function resource(): string
            {
                return 'invoice';
            }

            public function fields(): array
            {
                return [];
            }

            public function allows(CrudOperation $operation, RecordView $record): bool
            {
                return $operation !== CrudOperation::Update || $record->id !== '9';
            }
        };
        $crud = $this->invoices([
            new RunAction('approve', 'check2', ApproveInvoice::class),
            new RunAction('remind', 'bell', ApproveInvoice::class, update: false),
        ]);
        $actions = $this->actions([], [$locked], [$paid]);

        foreach (['8' => 'approve', '9' => 'approve'] as $id => $name) {
            try {
                $actions->run($crud, $name, (string) $id);
                self::fail(sprintf('%s of %s was done.', $name, $id));
            } catch (CrudFailure $failure) {
                self::assertSame(403, $failure->status);
            }
        }
        // Not a change of what may be edited: only the guards of the action.
        self::assertSame('approved 9', $actions->run($crud, 'remind', '9')?->title);
    }

    public function testAListenerRefuses(): void
    {
        $crud = $this->invoices([new RunAction('approve', 'check2', ApproveInvoice::class)]);
        $events = new EventDispatcher();
        $events->addListener(RecordChanging::class, static function (): never {
            throw new CrudFailure(409, 'Paid already.');
        });

        $this->expectExceptionMessage('Paid already.');

        try {
            $this->actions(events: $events)->run($crud, 'approve', '7');
        } finally {
            self::assertSame([], $this->runner->done);
        }
    }

    public function testItsButtonInTheListIsTheLiveActionOfTheList(): void
    {
        $action = new RunAction('approve', 'check2', ApproveInvoice::class, message: 'app.approve_question');
        $handler = new RunActionHandler(new IdentityTranslator(), new ServiceLocator([]));
        $crud = $this->invoices([$action]);

        $inList = $handler->button($action, new RecordView($crud, '7', [], RecordScreen::List));
        self::assertSame('run', $inList->attributes['data-live-action-param']);
        self::assertSame('approve', $inList->attributes['data-live-name-param']);
        self::assertSame('confirm#ask live#action', $inList->attributes['data-action']);
        self::assertSame('crud.invoice.action.approve', $inList->label);

        $onPage = $handler->button($action, new RecordView($crud, '7', [], RecordScreen::Details));
        self::assertSame('Crud:Run', $onPage->component);
        self::assertSame(['resource' => 'invoice', 'id' => '7', 'action' => 'approve'], $onPage->props);
    }

    /**
     * @param list<RecordAction> $actions
     */
    private function invoices(array $actions): AsCrud
    {
        $source = new DoctrineSource(stdClass::class);

        return new AsCrud('invoice', '/invoices', 'receipt', source: $source, actions: $actions);
    }

    /**
     * @param list<string> $roles
     * @param list<RecordGuard> $guards
     * @param list<RecordActionGuard> $actionGuards
     */
    private function actions(
        array $roles = [],
        array $guards = [],
        array $actionGuards = [],
        ?EventDispatcher $events = null,
    ): RecordActions {
        $events ??= new EventDispatcher();
        $events->addListener(RecordChanging::class, function (RecordChanging $event): void {
            $this->heard[] = sprintf('changing %s %s', $event->action, $event->record->id);
        });
        $events->addListener(RecordChanged::class, function (RecordChanged $event): void {
            $this->heard[] = sprintf('changed %s %s', $event->action, $event->id);
        });
        $recordGuards = new RecordGuards($guards, $actionGuards);
        $runner = $this->runner;
        $records = new class implements CrudRecords {
            public function read(AsCrud $crud, string $id): array
            {
                return ['id' => $id];
            }

            public function details(AsCrud $crud, string $id): array
            {
                return ['id' => $id, 'title' => null, 'rows' => [], 'item' => ['id' => $id]];
            }

            public function view(AsCrud $crud, string $id, RecordScreen $screen): RecordView
            {
                return new RecordView($crud, $id, ['id' => $id], $screen);
            }

            public function delete(AsCrud $crud, string $id): void
            {
            }
        };
        $urls = new UrlGenerator(new RouteCollection(), new RequestContext());

        return new RecordActions(
            new CrudRegistry(),
            $this->actor($roles),
            new ServiceLocator([
                RunActionHandler::class => static fn (): RunActionHandler => new RunActionHandler(
                    new IdentityTranslator(),
                    new ServiceLocator([ApproveInvoice::class => static fn (): RecordActionRunner => $runner]),
                ),
                RouteActionHandler::class => static fn (): RouteActionHandler => new RouteActionHandler($urls),
            ]),
            $recordGuards,
            new RecordChanges($records, $recordGuards, events: $events),
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
