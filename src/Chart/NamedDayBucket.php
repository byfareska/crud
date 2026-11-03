<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use DateTimeZone;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * The {@see DayBucket} named by byfareska_crud.chart.day_bucket: the index of its tag or its service id.
 */
final readonly class NamedDayBucket implements DayBucket, TimeBucket
{
    public function __construct(
        private string $name,
        #[AutowireLocator(DayBucket::TAG)]
        private ContainerInterface $buckets,
    ) {
    }

    public function periodSql(
        string $column,
        DateTimeZone $zone,
        Granularity $granularity,
        ?DateTimeZone $stored = new DateTimeZone('UTC'),
    ): string {
        $bucket = $this->buckets->has($this->name) ? $this->buckets->get($this->name) : null;
        if (!$bucket instanceof TimeBucket) {
            throw new LogicException(sprintf(
                'The day bucket "%s" (byfareska_crud.chart.day_bucket) gives days only: implement %s for periods.',
                $this->name,
                TimeBucket::class,
            ));
        }

        return $bucket->periodSql($column, $zone, $granularity, $stored);
    }

    public function sql(string $column, DateTimeZone $zone): string
    {
        if (!$this->buckets->has($this->name)) {
            throw new LogicException(sprintf(
                'No day bucket "%s": byfareska_crud.chart.day_bucket names a service tagged "%s" by its index or id.',
                $this->name,
                DayBucket::TAG,
            ));
        }

        $bucket = $this->buckets->get($this->name);
        assert($bucket instanceof DayBucket);

        return $bucket->sql($column, $zone);
    }
}
