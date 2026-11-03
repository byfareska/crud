<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Dashboard;

use Byfareska\Crud\Dashboard\DashboardWidget;
use Byfareska\Crud\Dashboard\LayoutPacker;
use Byfareska\Crud\Dashboard\WidgetPlacement;
use Byfareska\Crud\Dashboard\WidgetSize;
use PHPUnit\Framework\TestCase;

final class LayoutPackerTest extends TestCase
{
    public function testDefaultWidgetsByPriorityInTheFirstFreePlaces(): void
    {
        $packer = new LayoutPacker();
        $placements = $packer->pack([
            $this->widget('b', priority: 20, size: new WidgetSize(6, 2)),
            $this->widget('a', priority: 10, size: new WidgetSize(8, 2)),
            $this->widget('c', priority: 30, size: new WidgetSize(4, 2)),
            $this->widget('not-default', priority: 0, default: false),
        ]);

        self::assertSame(
            ['a', 'b', 'c'],
            array_map(static fn (WidgetPlacement $placement): string => $placement->widget, $placements),
        );
        self::assertSame([0, 0], [$placements[0]->x, $placements[0]->y]);
        // "b" does not fit next to "a" (8 + 6 > 12): under it; "c" fits next to "a".
        self::assertSame([0, 2], [$placements[1]->x, $placements[1]->y]);
        self::assertSame([8, 0], [$placements[2]->x, $placements[2]->y]);
        // The same ids every time: the default layout is made anew on every read.
        self::assertSame('a', $placements[0]->id);
    }

    public function testNothingOverlaps(): void
    {
        $packer = new LayoutPacker();
        $placements = [];
        for ($i = 0; $i < 10; ++$i) {
            $placement = $packer->place($placements, 'w' . $i, new WidgetSize(1 + $i % 5, 1 + $i % 3));
            self::assertFalse($packer->collides($placement, $placements));
            $placements[] = $placement;
        }
    }

    private function widget(
        string $key,
        int $priority,
        WidgetSize $size = new WidgetSize(3, 2),
        bool $default = true,
    ): DashboardWidget {
        return new DashboardWidget($key, 'X', $key, $key, 'box', size: $size, default: $default, priority: $priority);
    }
}
