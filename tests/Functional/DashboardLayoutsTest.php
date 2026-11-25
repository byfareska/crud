<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Dashboard\DashboardLayouts;
use Byfareska\Crud\Dashboard\Storage\DoctrineDashboardLayouts;
use Byfareska\Crud\Dashboard\WidgetPlacement;

/**
 * The layouts of the dashboards in the table of the database (the storage "doctrine"): the one for everybody is the
 * row of the owner "*", which no user is - a user whose id is "*" has a layout of their own.
 */
final class DashboardLayoutsTest extends PanelTestCase
{
    public function testAUserCalledAsEverybodyHasALayoutOfTheirOwn(): void
    {
        $this->boot();
        $this->seed();
        $layouts = $this->container()->get(DashboardLayouts::class);
        self::assertInstanceOf(DoctrineDashboardLayouts::class, $layouts);
        $shared = [new WidgetPlacement('a1', 'panel.clock', 0, 0, 3, 2)];
        $layouts->save(null, 'panel', $shared);

        self::assertNull($layouts->find('*', 'panel'), 'The layout for everybody is no layout of the user "*".');

        $layouts->save('*', 'panel', [new WidgetPlacement('b2', 'panel.note', 0, 0, 3, 2)]);
        $layouts->save('**', 'panel', [new WidgetPlacement('c3', 'panel.note', 3, 0, 3, 2)]);
        $layouts->delete('*', 'panel');

        self::assertSame(['a1'], $this->ids($layouts->find(null, 'panel')), 'Saved and deleted as "*" by its owner.');
        self::assertSame(['c3'], $this->ids($layouts->find('**', 'panel')));
    }

    /**
     * @param list<WidgetPlacement>|null $placements
     *
     * @return list<string>|null
     */
    private function ids(?array $placements): ?array
    {
        return $placements === null
            ? null
            : array_map(static fn (WidgetPlacement $placement): string => $placement->id, $placements);
    }
}
