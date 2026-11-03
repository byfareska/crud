<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart\Sql;

use DateTimeZone;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Byfareska\Crud\Chart\Granularity;
use Byfareska\Crud\Chart\TimeBucket;

#[AsTaggedItem('postgresql')]
final readonly class PostgreSqlDayBucket implements PlatformDayBucket, TimeBucket
{
    public function supports(AbstractPlatform $platform): bool
    {
        return $platform instanceof PostgreSQLPlatform;
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
        // A timestamp without a zone is first told its own; a timestamptz has one.
        $local = $stored instanceof \DateTimeZone
            ? sprintf("%s AT TIME ZONE '%s' AT TIME ZONE '%s'", $column, $stored->getName(), $zone->getName())
            : sprintf("%s AT TIME ZONE '%s'", $column, $zone->getName());

        return match ($granularity) {
            Granularity::Hour => sprintf("to_char(%s, 'YYYY-MM-DD HH24:\"00\"')", $local),
            Granularity::Day => sprintf("to_char(%s, 'YYYY-MM-DD')", $local),
            Granularity::Week => sprintf("to_char(date_trunc('week', %s), 'YYYY-MM-DD')", $local),
            // The Sunday of its week: the Monday of the day after, a day back.
            Granularity::SundayWeek => sprintf(
                "to_char(date_trunc('week', %s + interval '1 day') - interval '1 day', 'YYYY-MM-DD')",
                $local,
            ),
            Granularity::Month => sprintf("to_char(%s, 'YYYY-MM')", $local),
            Granularity::Quarter => sprintf("to_char(date_trunc('quarter', %s), 'YYYY-MM')", $local),
            Granularity::Year => sprintf("to_char(%s, 'YYYY')", $local),
        };
    }
}
