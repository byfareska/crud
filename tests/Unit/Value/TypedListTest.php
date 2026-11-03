<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Value;

use Byfareska\Crud\Dashboard\WidgetSize;
use Byfareska\Crud\Resource\CrudCell;
use Byfareska\Crud\Value\TypedValue;
use PHPUnit\Framework\TestCase;

final class TypedListTest extends TestCase
{
    public function testAListKeepsTheEntriesThatSayWhatTheyAre(): void
    {
        $paid = new TypedValue('status', 'paid');

        $cell = CrudCell::of(['plain', $paid, new \stdClass()]);

        self::assertSame('list', $cell['kind']);
        self::assertSame(['plain', $paid], $cell['value']);
    }

    public function testADayIsItsOwnKind(): void
    {
        self::assertSame(['kind' => 'day', 'value' => '2024-05-01'], CrudCell::of(new TypedValue('day', '2024-05-01')));
        self::assertSame('2024-05-01', (string) new TypedValue('day', '2024-05-01'), 'What a guard or a title sees.');
    }

    public function testAPresetIsAsWideOnAnotherGrid(): void
    {
        self::assertSame(6, WidgetSize::small()->onGridOf(24)->w);
        self::assertSame(24, WidgetSize::extraLarge()->onGridOf(24)->w);
        self::assertTrue(WidgetSize::medium()->onGridOf(12)->equals(WidgetSize::medium()));
    }
}
