<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use DateTimeZone;

/**
 * The SQL of one database that gives the day of a date column ("2026-10-19") in a time zone - what a count by day
 * groups by. The column holds UTC without a zone.
 *
 * Every service of this interface is tagged {@see self::TAG} and can be chosen by byfareska_crud.chart.day_bucket - by
 * the index of its tag (postgresql, mysql, sqlite: {@see Sql}) or its service id. By default the one of the platform of
 * Doctrine's connection ({@see Sql\DoctrineDayBucket}).
 */
interface DayBucket
{
    public const string TAG = 'byfareska_crud.day_bucket';

    public function sql(string $column, DateTimeZone $zone): string;
}
