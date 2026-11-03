<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Action;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\AsksForInput;
use Byfareska\Crud\Action\ConfirmInput;
use Byfareska\Crud\Event\BulkActionDone;
use Byfareska\Crud\Event\BulkActionRunning;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Action\BulkAction;
use Byfareska\Crud\Action\BulkActions;
use Byfareska\Crud\Action\BulkSelection;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\Tests\Unit\Fixtures\TaggingBulkHandler;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
