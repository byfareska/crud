<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Dashboard;

use Byfareska\Crud\Dashboard\WidgetSetting;
use Byfareska\Crud\Dashboard\WidgetSettingType;
use PHPUnit\Framework\TestCase;

/**
 * What is kept of a value of a setting: one of its kind, else its default.
 */
final class WidgetSettingTest extends TestCase
{
    public function testANumberWithinItsBoundsByItsStep(): void
    {
        $threshold = new WidgetSetting(
            'threshold',
            'app.threshold',
            WidgetSettingType::Number,
            default: '10',
            min: 0,
            max: 100,
            step: 5,
        );

        self::assertSame('25', $threshold->accept('25'));
        self::assertSame('25', $threshold->accept(25.0));
        self::assertSame('10', $threshold->accept('27'), 'Not by its step.');
        self::assertSame('10', $threshold->accept('105'), 'Over its largest.');
        self::assertSame('10', $threshold->accept('-5'), 'Under its smallest.');
        self::assertSame('10', $threshold->accept('ten'));
        self::assertSame('2.5', new WidgetSetting('rate', 'app.rate', WidgetSettingType::Number)->accept('2.5'));
    }

    public function testASwitchIsOnOrOff(): void
    {
        $compact = new WidgetSetting('compact', 'app.compact', WidgetSettingType::Toggle);

        self::assertSame('1', $compact->accept('1'));
        self::assertSame('1', $compact->accept(true));
        self::assertSame('', $compact->accept(null));
        self::assertSame('', $compact->accept('yes please'));
    }

    public function testADayAndAColour(): void
    {
        $since = new WidgetSetting('since', 'app.since', WidgetSettingType::Date, default: '2026-01-01');
        $accent = new WidgetSetting('accent', 'app.accent', WidgetSettingType::Color, default: '#000000');

        self::assertSame('2026-11-02', $since->accept('2026-11-02'));
        self::assertSame('2026-01-01', $since->accept('2026-02-30'), 'No such day.');
        self::assertSame('#ff8800', $accent->accept('#FF8800'));
        self::assertSame('#000000', $accent->accept('red'));
    }
}
