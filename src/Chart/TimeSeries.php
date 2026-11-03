<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Counts (or sums) by an hour, a day, a week, a month, a quarter or a year ({@see Granularity}), in the zone of the
 * chart ({@see ChartTimezone}): the SQL that buckets a column (by the database, {@see TimeBucket}), the periods of the
 * last N with nothing missing, their labels in the language of the request.
 *
 *     $week = Granularity::Week;
 *     $sql = sprintf('SELECT %s AS period, SUM(total) FROM orders WHERE created_at >= :since GROUP BY period',
 *         $series->bucket('created_at', $week));
 *     $since = $series->since(12, $week)->format('Y-m-d H:i:s');
 *     $values = $series->fill($connection->fetchAllKeyValue($sql, ['since' => $since]), 12, $week);
 *     $labels = $series->labels(array_keys($values), $week);
 *     $chart = $charts->line($labels, [['data' => array_values($values), ...]]);
 *
 * The column holds its times in byfareska_crud.chart.stored_timezone (UTC by default; null for a column with a zone of
 * its own, PostgreSQL timestamptz).
 */
final readonly class TimeSeries
{
    public function __construct(
        private ChartTimezone $timezone,
        private LocaleSwitcher $locale,
        private ?DayBucket $bucket = null,
        #[Autowire(param: 'byfareska_crud.chart.stored_timezone')]
        private ?string $storedTimezone = 'UTC',
    ) {
    }

    /**
     * SQL giving the period of the column ("2026-10-19 14:00", "2026-10-19", "2026-10") in the zone of the chart.
     */
    public function bucket(string $column, Granularity $granularity = Granularity::Day): string
    {
        if (!$this->bucket instanceof TimeBucket) {
            throw new LogicException(
                'No SQL of periods: set byfareska_crud.chart.day_bucket (postgresql, mysql, sqlite or a TimeBucket '
                . 'service).',
            );
        }

        return $this->bucket->periodSql(
            $column,
            $this->zone(),
            $granularity,
            $this->storedTimezone === null ? null : new DateTimeZone($this->storedTimezone),
        );
    }

    /**
     * The first moment of the first of the last $count periods (the current one included), in the zone the column
     * holds its times in - what a query starts from.
     */
    public function since(
        int $count,
        Granularity $granularity = Granularity::Day,
        ?DateTimeImmutable $now = null,
    ): DateTimeImmutable {
        $first = $this->start(($now ?? new DateTimeImmutable())->setTimezone($this->zone()), $granularity);
        $back = max(0, $count - 1);
        if ($back > 0) {
            $first = $first->modify($granularity->back($back));
        }

        return $first->setTimezone(new DateTimeZone($this->storedTimezone ?? 'UTC'));
    }

    /**
     * The last $count periods, the current one included, oldest first, as the SQL of bucket() tells them.
     *
     * @return list<string>
     */
    public function periods(
        int $count,
        Granularity $granularity = Granularity::Day,
        ?DateTimeImmutable $now = null,
    ): array {
        $period = $this->start(($now ?? new DateTimeImmutable())->setTimezone($this->zone()), $granularity);
        $back = max(0, $count - 1);
        if ($back > 0) {
            $period = $period->modify($granularity->back($back));
        }

        $list = [];
        for ($i = 0; $i < $count; ++$i) {
            $text = $period->format($granularity->format());
            // The hour the clock is turned back is the same text twice ("02:00", again): one period, as the SQL of
            // bucket() counts both in it.
            if ($text !== ($list[array_key_last($list) ?? -1] ?? null)) {
                $list[] = $text;
            }
            $period = $period->modify($granularity->step());
        }

        return $list;
    }

    /**
     * The values of the last $count periods, a zero where nothing was counted: counts stay whole numbers, sums keep
     * their fractions.
     *
     * @param array<array-key, int|string|float> $values by period, as bucket() tells it
     *
     * @return array<string, int|float>
     */
    public function fill(
        array $values,
        int $count,
        Granularity $granularity = Granularity::Day,
        ?DateTimeImmutable $now = null,
    ): array {
        $filled = [];
        foreach ($this->periods($count, $granularity, $now) as $period) {
            $value = $values[$period] ?? 0;
            $filled[$period] = is_numeric($value) ? $value + 0 : 0;
        }

        return $filled;
    }

    /**
     * Short labels of periods for an axis ("14:00", "19 paź", "paź 2026", "IV kw. 2026").
     *
     * @param list<string> $periods as periods() gives them
     * @param string|null $pattern an ICU pattern of your own ("EEE d", "MMMM"); null = Granularity::pattern()
     *
     * @return list<string>
     */
    public function labels(array $periods, Granularity $granularity = Granularity::Day, ?string $pattern = null): array
    {
        $formatter = new IntlDateFormatter(
            $this->locale->getLocale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC',
            null,
            $pattern ?? $granularity->pattern(),
        );
        $utc = new DateTimeZone('UTC');

        return array_map(
            static fn (string $period): string => (string) $formatter->format(new DateTimeImmutable(
                match ($granularity) {
                    Granularity::Month, Granularity::Quarter => $period . '-01',
                    Granularity::Year => $period . '-01-01',
                    default => $period,
                },
                $utc,
            )),
            $periods,
        );
    }

    public function zone(): DateTimeZone
    {
        return $this->timezone->zone();
    }

    /** The first moment of the period the moment is in. */
    private function start(DateTimeImmutable $moment, Granularity $granularity): DateTimeImmutable
    {
        return match ($granularity) {
            Granularity::Hour => $moment->setTime((int) $moment->format('G'), 0),
            Granularity::Day => $moment->setTime(0, 0),
            Granularity::Week => $moment->modify('monday this week')->setTime(0, 0),
            // Sunday to Saturday: the Sunday of the week, today when it is one.
            Granularity::SundayWeek => $moment->modify(sprintf('-%d days', (int) $moment->format('w')))->setTime(0, 0),
            Granularity::Month => $moment->modify('first day of this month')->setTime(0, 0),
            Granularity::Quarter => $moment->setDate(
                (int) $moment->format('Y'),
                intdiv((int) $moment->format('n') - 1, 3) * 3 + 1,
                1,
            )->setTime(0, 0),
            Granularity::Year => $moment->setDate((int) $moment->format('Y'), 1, 1)->setTime(0, 0),
        };
    }
}
