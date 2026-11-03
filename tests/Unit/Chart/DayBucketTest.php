<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Chart;

use Byfareska\Crud\Chart\Sql\MySqlDayBucket;
use Byfareska\Crud\Chart\Sql\PostgreSqlDayBucket;
use Byfareska\Crud\Chart\Sql\SqliteDayBucket;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DayBucketTest extends TestCase
{
    public function testPostgreSqlConvertsTheColumnFromUtcToTheZone(): void
    {
        self::assertSame(
            "to_char(created_at AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Warsaw', 'YYYY-MM-DD')",
            new PostgreSqlDayBucket()->sql('created_at', new DateTimeZone('Europe/Warsaw')),
        );
    }

    public function testAZoneWithOneOffsetNeedsNoCase(): void
    {
        self::assertSame(
            "date(created_at, '+0 seconds')",
            new SqliteDayBucket()->sql('created_at', new DateTimeZone('UTC')),
        );
        self::assertSame(
            'DATE(created_at + INTERVAL 19800 SECOND)',
            new MySqlDayBucket()->sql('created_at', new DateTimeZone('Asia/Kolkata')),
        );
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testSqliteGivesTheDayInTheZoneOnBothSidesOfAChangeOfTime(): void
    {
        $sql = new SqliteDayBucket()->sql('created_at', new DateTimeZone('Europe/Warsaw'));
        $pdo = new PDO('sqlite::memory:');
        $day = static function (string $utc) use ($pdo, $sql): mixed {
            $statement = $pdo->query(sprintf("SELECT %s FROM (SELECT '%s' AS created_at)", $sql, $utc));

            return ($statement ?: throw new RuntimeException('The query failed.'))->fetchColumn();
        };

        self::assertSame('2026-10-20', $day('2026-10-19 22:30:00')); // summer: +2
        self::assertSame('2026-10-19', $day('2026-10-19 21:59:59'));
        self::assertSame('2026-12-20', $day('2026-12-19 23:00:00')); // winter: +1
        self::assertSame('2026-12-19', $day('2026-12-19 22:59:59'));
        self::assertSame('1999-01-01', $day('1998-12-31 23:00:00'));
    }
}
