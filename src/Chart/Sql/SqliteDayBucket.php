<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart\Sql;

use DateTimeZone;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Byfareska\Crud\Chart\Granularity;
use Byfareska\Crud\Chart\TimeBucket;

/**
 * SQLite, which knows no time zones but UTC and the one of the machine: the offsets of the zone are written into the
 * query ({@see ZoneOffsets}). Dates are the texts Doctrine stores ("2026-10-19 23:30:00").
 */
#[AsTaggedItem('sqlite')]
final readonly class SqliteDayBucket implements PlatformDayBucket, TimeBucket
{
    public function supports(AbstractPlatform $platform): bool
    {
        return $platform instanceof SQLitePlatform;
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
            static fn (int $offset): string => match ($granularity) {
                Granularity::Hour => sprintf("strftime('%%Y-%%m-%%d %%H:00', %s, '%+d seconds')", $column, $offset),
                Granularity::Day => sprintf("date(%s, '%+d seconds')", $column, $offset),
                // The Monday of its week: the Sunday it ends with, six days back.
                Granularity::Week => sprintf("date(%s, '%+d seconds', 'weekday 0', '-6 days')", $column, $offset),
                // The Sunday of its week: the Saturday it ends with, six days back.
                Granularity::SundayWeek => sprintf(
                    "date(%s, '%+d seconds', 'weekday 6', '-6 days')",
                    $column,
                    $offset,
                ),
                Granularity::Month => sprintf("strftime('%%Y-%%m', %s, '%+d seconds')", $column, $offset),
                // The first month of its quarter.
                Granularity::Quarter => sprintf(
                    "strftime('%%Y', %1\$s, '%2\$+d seconds') || '-' || printf('%%02d', "
                    . "(CAST(strftime('%%m', %1\$s, '%2\$+d seconds') AS INTEGER) - 1) / 3 * 3 + 1)",
                    $column,
                    $offset,
                ),
                Granularity::Year => sprintf("strftime('%%Y', %s, '%+d seconds')", $column, $offset),
            },
            $stored,
        );
    }
}
