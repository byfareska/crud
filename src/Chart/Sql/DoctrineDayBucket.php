<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart\Sql;

use Byfareska\Crud\Chart\DayBucket;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Byfareska\Crud\Chart\Granularity;
use Byfareska\Crud\Chart\TimeBucket;

/**
 * The SQL of the database Doctrine's connection is to, by the first {@see PlatformDayBucket} that supports its
 * platform - the default when there is Doctrine.
 */
final readonly class DoctrineDayBucket implements DayBucket, TimeBucket
{
    /**
     * @param iterable<PlatformDayBucket> $buckets
     */
    public function __construct(
        private Connection $connection,
        #[AutowireIterator(PlatformDayBucket::TAG)]
        private iterable $buckets,
    ) {
    }

    public function periodSql(
        string $column,
        DateTimeZone $zone,
        Granularity $granularity,
        ?DateTimeZone $stored = new DateTimeZone('UTC'),
    ): string {
        $platform = $this->connection->getDatabasePlatform();
        foreach ($this->buckets as $bucket) {
            if ($bucket instanceof TimeBucket && $bucket->supports($platform)) {
                return $bucket->periodSql($column, $zone, $granularity, $stored);
            }
        }

        throw new LogicException(sprintf(
            'No time bucket for %s: add a %s service implementing %s too.',
            $platform::class,
            PlatformDayBucket::class,
            TimeBucket::class,
        ));
    }

    public function sql(string $column, DateTimeZone $zone): string
    {
        $platform = $this->connection->getDatabasePlatform();
        foreach ($this->buckets as $bucket) {
            if ($bucket->supports($platform)) {
                return $bucket->sql($column, $zone);
            }
        }

        throw new LogicException(sprintf(
            'No day bucket for %s: add a %s service of your own or set byfareska_crud.chart.day_bucket.',
            $platform::class,
            PlatformDayBucket::class,
        ));
    }
}
