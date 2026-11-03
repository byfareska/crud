<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Twig;

use Byfareska\Crud\Chart\ChartTimezone;
use Byfareska\Crud\Twig\WallClockRuntime;
use DateTimeImmutable;
use DateTimeZone;
use MessageFormatter;
use PHPUnit\Framework\TestCase;

/**
 * A moment is shown in the zone of the panel, though ICU formats in the zone of the server.
 */
final class WallClockRuntimeTest extends TestCase
{
    private string $zone;

    protected function setUp(): void
    {
        $this->zone = date_default_timezone_get();
        // The server in UTC, the panel in Warsaw.
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->zone);
    }

    public function testAMomentIsShownByTheClockOfThePanel(): void
    {
        $clock = $this->clock('Europe/Warsaw');
        // 22:30 UTC on Sunday is half past midnight on Monday in Warsaw (summer).
        $written = new DateTimeImmutable('2026-10-18T22:30:00+00:00');

        $format = static fn (DateTimeImmutable $at): string
            => trim((string) MessageFormatter::formatMessage('en', '{at, date, yyyy-MM-dd HH:mm}', ['at' => $at]));

        self::assertSame('2026-10-18 22:30', $format($written), 'ICU alone: the zone of the server.');
        self::assertSame('2026-10-19 00:30', $format($clock->of($written)));
    }

    public function testADayIsThatDay(): void
    {
        $day = $this->clock('Pacific/Auckland')->of('2026-10-19');

        self::assertSame(
            '2026-10-19',
            trim((string) MessageFormatter::formatMessage('en', '{at, date, yyyy-MM-dd}', ['at' => $day])),
        );
    }

    private function clock(string $zone): WallClockRuntime
    {
        return new WallClockRuntime(new readonly class ($zone) implements ChartTimezone {
            public function __construct(private string $zone)
            {
            }

            public function zone(): DateTimeZone
            {
                return new DateTimeZone($this->zone);
            }
        });
    }
}
