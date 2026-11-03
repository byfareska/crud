<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use DateTimeZone;

/**
 * The SQL of one database that gives the period of a date column in a time zone ({@see Granularity}: the hour, the
 * day, the week, the month) - what a count by period groups by. The bundle's buckets of PostgreSQL, MySQL and SQLite
 * are TimeBuckets as well as DayBuckets; so are the ones that choose among them.
 */
interface TimeBucket
{
    /**
     * @param DateTimeZone $zone the zone the periods are in (the one dates are shown in)
     * @param DateTimeZone|null $stored the zone the column holds its times in, without one (Doctrine's datetime: the
     *     zone of PHP when they were written - UTC when it is set so); null for a column with a zone of its own
     *     (PostgreSQL timestamptz)
     */
    public function periodSql(
        string $column,
        DateTimeZone $zone,
        Granularity $granularity,
        ?DateTimeZone $stored = new DateTimeZone('UTC'),
    ): string;
}
