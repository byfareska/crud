<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Chart;

use Byfareska\Crud\Chart\ChartTimezone;
use Byfareska\Crud\Chart\Granularity;
use Byfareska\Crud\Chart\Sql\MySqlDayBucket;
use Byfareska\Crud\Chart\Sql\PostgreSqlDayBucket;
use Byfareska\Crud\Chart\Sql\SqliteDayBucket;
use Byfareska\Crud\Chart\TimeSeries;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class TimeSeriesTest extends TestCase
{
    public function testThePeriodsOfEachGranularity(): void
    {
        $now = new DateTimeImmutable('2026-10-21 14:30', new DateTimeZone('Europe/Warsaw'));
        $series = $this->series();

        self::assertSame(['2026-10-21 13:00', '2026-10-21 14:00'], $series->periods(2, Granularity::Hour, $now));
        self::assertSame(['2026-10-12', '2026-10-19'], $series->periods(2, Granularity::Week, $now), 'From Monday.');
        self::assertSame(['2026-09', '2026-10'], $series->periods(2, Granularity::Month, $now));
        self::assertSame(
            ['2026-09' => 12.5, '2026-10' => 0],
            $series->fill(['2026-09' => '12.50'], 2, Granularity::Month, $now),
        );
        // The first of September in Warsaw, as the column has it (UTC).
        self::assertSame('2026-08-31 22:00:00', $series->since(2, Granularity::Month, $now)->format('Y-m-d H:i:s'));
        self::assertSame(['wrz 2026'], $series->labels(['2026-09'], Granularity::Month));
    }

    public function testTheHourTheClockIsTurnedBackIsOnePeriod(): void
    {
        // Warsaw, 25 October 2026: 03:00 summer time is 02:00 winter time - "02:00" twice.
        $now = new DateTimeImmutable('2026-10-25 04:30', new DateTimeZone('Europe/Warsaw'));

        $periods = $this->series()->periods(5, Granularity::Hour, $now);

        self::assertSame($periods, array_values(array_unique($periods)), 'No period twice.');
        self::assertContains('2026-10-25 02:00', $periods);
    }

    public function testQuartersYearsAndWeeksFromSunday(): void
    {
        // A Wednesday.
        $now = new DateTimeImmutable('2026-10-21 14:30', new DateTimeZone('Europe/Warsaw'));
        $series = $this->series();

        self::assertSame(['2026-10-11', '2026-10-18'], $series->periods(2, Granularity::SundayWeek, $now));
        self::assertSame(
            ['2026-10-18'],
            $series->periods(1, Granularity::SundayWeek, new DateTimeImmutable('2026-10-18 09:00')),
            'A Sunday starts its own week.',
        );
        self::assertSame(['2026-04', '2026-07', '2026-10'], $series->periods(3, Granularity::Quarter, $now));
        self::assertSame(['2025', '2026'], $series->periods(2, Granularity::Year, $now));
        self::assertSame('2026-06-30 22:00:00', $series->since(2, Granularity::Quarter, $now)->format('Y-m-d H:i:s'));
        self::assertSame(['2026'], $series->labels(['2026'], Granularity::Year));
        self::assertSame(['październik'], $series->labels(['2026-10'], Granularity::Month, 'LLLL'), 'Its own pattern.');
    }

    public function testTheSqlOfAPeriodOnSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $zone = new DateTimeZone('Europe/Warsaw');
        $bucket = new SqliteDayBucket();
        $at = static function (
            Granularity $granularity,
            string $moment,
            string $stored = 'UTC',
        ) use (
            $pdo,
            $bucket,
            $zone
): string {
            $sql = $bucket->periodSql("'" . $moment . "'", $zone, $granularity, new DateTimeZone($stored));
            $result = $pdo->query('SELECT ' . $sql);

            return $result === false ? '' : (string) $result->fetchColumn();
        };

        // 23:30 UTC on Sunday is 01:30 on Monday in Warsaw (summer).
        self::assertSame('2026-10-19 01:00', $at(Granularity::Hour, '2026-10-18 23:30:00'));
        self::assertSame('2026-10-19', $at(Granularity::Day, '2026-10-18 23:30:00'));
        self::assertSame('2026-10-19', $at(Granularity::Week, '2026-10-18 23:30:00'));
        self::assertSame('2026-10-12', $at(Granularity::Week, '2026-10-18 20:00:00'));
        self::assertSame('2026-10', $at(Granularity::Month, '2026-10-31 21:00:00'));
        self::assertSame('2026-11', $at(Granularity::Month, '2026-10-31 23:30:00'), 'Winter time by then: +1.');
        // 23:30 UTC on Saturday is 01:30 on Sunday in Warsaw: a week of its own from Sunday.
        self::assertSame('2026-10-18', $at(Granularity::SundayWeek, '2026-10-17 23:30:00'));
        self::assertSame('2026-10-11', $at(Granularity::SundayWeek, '2026-10-17 20:00:00'));
        self::assertSame('2026-10', $at(Granularity::Quarter, '2026-11-15 12:00:00'));
        self::assertSame('2027-01', $at(Granularity::Quarter, '2026-12-31 23:30:00'), 'Another year in Warsaw.');
        self::assertSame('2026', $at(Granularity::Year, '2026-06-01 12:00:00'));
        // Written in the zone of PHP (Warsaw): already the time of the periods.
        self::assertSame('2026-10-18', $at(Granularity::Day, '2026-10-18 23:30:00', 'Europe/Warsaw'));
    }

    public function testTheSqlOfOtherDatabases(): void
    {
        $zone = new DateTimeZone('Europe/Warsaw');

        self::assertSame(
            "to_char(date_trunc('week', created_at AT TIME ZONE 'Europe/Warsaw'), 'YYYY-MM-DD')",
            new PostgreSqlDayBucket()->periodSql('created_at', $zone, Granularity::Week, null),
            'A timestamptz has a zone of its own.',
        );
        self::assertStringContainsString(
            "DATE_FORMAT((created_at + INTERVAL",
            new MySqlDayBucket()->periodSql('created_at', $zone, Granularity::Month),
        );
    }

    private function series(): TimeSeries
    {
        $zone = new class implements ChartTimezone {
            public function zone(): DateTimeZone
            {
                return new DateTimeZone('Europe/Warsaw');
            }
        };

        return new TimeSeries($zone, new LocaleSwitcher('pl', []));
    }
}
