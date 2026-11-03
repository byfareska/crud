<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart\Sql;

use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Byfareska\Crud\Chart\Granularity;
use Byfareska\Crud\Chart\TimeBucket;

/**
 * MySQL and MariaDB. Named zones of CONVERT_TZ() need the zone tables loaded into the server, so the offsets of the
 * zone are written into the query instead ({@see ZoneOffsets}).
 */
#[AsTaggedItem('mysql')]
final readonly class MySqlDayBucket implements PlatformDayBucket, TimeBucket
{
    public function supports(AbstractPlatform $platform): bool
    {
        return $platform instanceof AbstractMySQLPlatform;
    }

    public function sql(string $column, DateTimeZone $zone): string
    {
        return $this->periodSql($column, $zone, Granularity::Day);
    }

    public function periodSql(
        string $column,
        DateTimeZone $zone,
        Granularity $granularity,
        ?DateTimeZone $stored = new DateTimeZone('UTC'),
    ): string {
        return ZoneOffsets::sql(
            $column,
            $zone,
            static function (int $offset) use ($column, $granularity): string {
                $local = sprintf('(%s + INTERVAL %d SECOND)', $column, $offset);

                return match ($granularity) {
                    Granularity::Hour => sprintf("DATE_FORMAT(%s, '%%Y-%%m-%%d %%H:00')", $local),
                    Granularity::Day => sprintf('DATE(%s + INTERVAL %d SECOND)', $column, $offset),
                    // The Monday of its week.
                    Granularity::Week => sprintf('DATE(%1$s - INTERVAL WEEKDAY(%1$s) DAY)', $local),
                    // The Sunday of its week (DAYOFWEEK: Sunday is 1).
                    Granularity::SundayWeek => sprintf('DATE(%1$s - INTERVAL (DAYOFWEEK(%1$s) - 1) DAY)', $local),
                    Granularity::Month => sprintf("DATE_FORMAT(%s, '%%Y-%%m')", $local),
                    // The first month of its quarter.
                    Granularity::Quarter => sprintf(
                        "CONCAT(YEAR(%1\$s), '-', LPAD((QUARTER(%1\$s) - 1) * 3 + 1, 2, '0'))",
                        $local,
                    ),
                    Granularity::Year => sprintf("DATE_FORMAT(%s, '%%Y')", $local),
                };
            },
            // A column of MySQL has no zone of its own: as written.
            $stored,
        );
    }
}
