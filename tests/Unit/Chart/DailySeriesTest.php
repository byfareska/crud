<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Chart;

use Byfareska\Crud\Chart\DailySeries;
use Byfareska\Crud\Chart\DayBucket;
use Byfareska\Crud\Chart\Sql\PostgreSqlDayBucket;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

final class DailySeriesTest extends TestCase
{
    public function testTheDaysAreThoseOfTheZoneDatesAreShownIn(): void
    {
        // 23:30 UTC on the 19th is already the 20th in Warsaw.
        $now = new DateTimeImmutable('2026-10-19 23:30', new DateTimeZone('UTC'));

        self::assertSame(['2026-10-18', '2026-10-19', '2026-10-20'], $this->series('Europe/Warsaw')->days(3, $now));
        self::assertSame(['2026-10-17', '2026-10-18', '2026-10-19'], $this->series('UTC')->days(3, $now));
    }

    public function testAPeriodStartsAtMidnightOfItsFirstDayInThatZone(): void
    {
        $now = new DateTimeImmutable('2026-10-19 12:00', new DateTimeZone('UTC'));

        self::assertSame('2026-10-16 22:00', $this->series('Europe/Warsaw')->since(3, $now)->format('Y-m-d H:i'));
    }

    public function testFillsTheDaysWithoutAnythingWithZeros(): void
    {
        $now = new DateTimeImmutable('2026-10-19 12:00', new DateTimeZone('UTC'));

        self::assertSame(
            ['2026-10-17' => 0, '2026-10-18' => 4, '2026-10-19' => 0],
            $this->series('UTC')->fill(['2026-10-18' => '4', '2026-10-01' => 9], 3, $now),
        );
    }

    public function testASumKeepsItsFractions(): void
    {
        $now = new DateTimeImmutable('2026-10-19 12:00', new DateTimeZone('UTC'));

        self::assertSame(
            ['2026-10-18' => 12.5, '2026-10-19' => 0.99],
            $this->series('UTC')->fill(['2026-10-18' => '12.50', '2026-10-19' => 0.99], 2, $now),
        );
    }

    public function testBucketsAColumnInThatZoneWithTheSqlOfTheDatabase(): void
    {
        self::assertSame(
            "to_char(created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Warsaw', 'YYYY-MM-DD')",
            $this->series('Europe/Warsaw', new PostgreSqlDayBucket())->bucket('created_at'),
        );
    }

    public function testWithoutTheSqlOfADatabaseThereIsNoBucket(): void
    {
        $this->expectException(LogicException::class);

        $this->series('UTC')->bucket('created_at');
    }

    private function series(string $zone, ?DayBucket $bucket = null): DailySeries
    {
        $twig = new Environment(new ArrayLoader());
        $twig->getExtension(CoreExtension::class)->setTimezone($zone);

        return new DailySeries($twig, new LocaleSwitcher('pl', []), $bucket);
    }
}
