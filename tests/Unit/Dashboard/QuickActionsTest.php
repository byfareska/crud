<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Dashboard;

use Byfareska\Crud\CommandPalette\CommandPaletteSource;
use Byfareska\Crud\CommandPalette\PaletteEntry;
use Byfareska\Crud\Dashboard\QuickActions;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Tests\Unit\Fixtures\RecordingActor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The quick actions of a dashboard are those of its panel - also on a board of the panel - and of none.
 */
final class QuickActionsTest extends TestCase
{
    public function testADashboardHasTheQuickActionsOfItsPanelAndOfNone(): void
    {
        $actions = $this->actions();

        self::assertSame(['crud.gizmo.create', 'app.import.create'], $this->keys($actions->available(board: 'admin')));
        self::assertSame(['crud.thing.create', 'app.import.create'], $this->keys($actions->available(board: 'panel')));
    }

    public function testABoardOfAPanelHasTheQuickActionsOfThatPanel(): void
    {
        self::assertSame(
            ['crud.gizmo.create', 'app.import.create'],
            $this->keys($this->actions()->available(board: 'sales')),
        );
    }

    /**
     * @param list<array{entry: PaletteEntry, icon: string}> $actions
     *
     * @return list<string>
     */
    private function keys(array $actions): array
    {
        return array_map(static fn (array $action): string => $action['entry']->key, $actions);
    }

    private function actions(): QuickActions
    {
        $source = new class implements CommandPaletteSource {
            public function entries(): iterable
            {
                yield new PaletteEntry('crud.gizmo.create', 'g', 'New gizmo', 'plus', routeName: 'n', scope: 'admin');
                yield new PaletteEntry('crud.thing.create', 'g', 'New thing', 'plus', routeName: 'n', scope: 'panel');
                yield new PaletteEntry('app.import.create', 'g', 'Import', 'upload', routeName: 'i');
            }
        };
        $requests = new RequestStack();
        $config = ['panel' => [], 'admin' => ['boards' => ['sales' => []]]];

        return new QuickActions([$source], new Scopes(
            // @phpstan-ignore argument.type (the configuration as the bundle has it, with its defaults)
            $config,
            'layout.html.twig',
            'daisyui',
            [],
            $requests,
            new RecordingActor([]),
            new CurrentCrud($requests),
        ));
    }
}
