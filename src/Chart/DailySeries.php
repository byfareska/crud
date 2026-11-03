<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use LogicException;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * Counts by day, in the time zone dates are shown in (the one of whoever is signed in): the SQL that buckets a column,
 * the days of a period with nothing missing, their labels in the language of the request.
 *
 * Dates are stored in UTC without a zone. The SQL is the one of the database, given by a {@see DayBucket}. For other
 * periods (hours, weeks, months), dates stored in another zone or columns with a zone: {@see TimeSeries}.
 */
final readonly class DailySeries
{
    public function __construct(
        private Environment $twig,
        private LocaleSwitcher $locale,
        private ?DayBucket $dayBucket = null,
        private ?ChartTimezone $timezone = null,
    ) {
    }

    /**
     * SQL giving the day of the column ("2026-10-19") in the zone dates are shown in.
     */
    public function bucket(string $column): string
    {
        if (!$this->dayBucket instanceof DayBucket) {
            throw new LogicException(
                'No SQL for days: set byfareska_crud.chart.day_bucket (postgresql, mysql, sqlite or a DayBucket '
                . 'service).',
            );
        }

        return $this->dayBucket->sql($column, $this->zone());
    }

    /**
     * The first moment of the first of the last $days days (today included), in UTC - what a query starts from.
     */
    public function since(int $days, ?DateTimeImmutable $now = null): DateTimeImmutable
    {
        $first = $this->today($now)->modify(sprintf('-%d days', max(0, $days - 1)));

        return $first->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The last $days days, today included, oldest first.
     *
     * @return list<string> "Y-m-d"
     */
    public function days(int $days, ?DateTimeImmutable $now = null): array
    {
        $today = $this->today($now);
        $list = [];
        for ($i = $days - 1; $i >= 0; --$i) {
            $list[] = $today->modify(sprintf('-%d days', $i))->format('Y-m-d');
        }

        return $list;
    }

    /**
     * The values of the last $days days, a zero where nothing was counted: counts stay whole numbers, sums keep their
     * fractions (a SUM of amounts - "12.50" from the database is 12.5).
     *
     * @param array<array-key, int|string|float> $counts by "Y-m-d"
     *
     * @return array<string, int|float>
     */
    public function fill(array $counts, int $days, ?DateTimeImmutable $now = null): array
    {
        $filled = [];
        foreach ($this->days($days, $now) as $day) {
            $value = $counts[$day] ?? 0;
            // A number as the database gives it: "4" is 4, "12.50" is 12.5; anything else counts nothing.
            $filled[$day] = is_numeric($value) ? $value + 0 : 0;
        }

        return $filled;
    }

    /**
     * Short labels of days for an axis ("19 paź", "Oct 19").
     *
     * @param list<string> $days "Y-m-d"
     *
     * @return list<string>
     */
    public function labels(array $days): array
    {
        $formatter = new IntlDateFormatter(
            $this->locale->getLocale(),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC',
            null,
            'd MMM',
        );

        $utc = new DateTimeZone('UTC');
        $label = static fn (string $day): string => (string) $formatter->format(new DateTimeImmutable($day, $utc));

        return array_map($label, $days);
    }

    public function zone(): DateTimeZone
    {
        // The zone of the charts (ChartTimezone), by default the one Twig shows dates in (twig.date.timezone).
        return $this->timezone?->zone() ?? $this->twig->getExtension(CoreExtension::class)->getTimezone();
    }

    private function today(?DateTimeImmutable $now): DateTimeImmutable
    {
        return ($now ?? new DateTimeImmutable())->setTimezone($this->zone())->setTime(0, 0);
    }
}
