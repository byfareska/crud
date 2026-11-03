<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

/**
 * What a time series counts by: an hour, a day, a week (from Monday, ISO 8601; or from Sunday, as in the US), a
 * month, a quarter or a year. Each period is told by the text its SQL gives ({@see TimeBucket}) and
 * {@see TimeSeries} makes: "2026-10-19 14:00", "2026-10-19", the first day of the week "2026-10-19", "2026-10", the
 * first month of the quarter "2026-10", "2026".
 */
enum Granularity: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    /** A week from Sunday to Saturday. */
    case SundayWeek = 'sunday_week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /** The text of a period, a date() format. */
    public function format(): string
    {
        return match ($this) {
            self::Hour => 'Y-m-d H:00',
            self::Day, self::Week, self::SundayWeek => 'Y-m-d',
            self::Month, self::Quarter => 'Y-m',
            self::Year => 'Y',
        };
    }

    /** The label of a period on an axis, an ICU pattern ("14:00", "19 paź", "paź 2026"). */
    public function pattern(): string
    {
        return match ($this) {
            self::Hour => 'HH:mm',
            self::Day, self::Week, self::SundayWeek => 'd MMM',
            self::Month => 'LLL yyyy',
            self::Quarter => 'QQQ yyyy',
            self::Year => 'yyyy',
        };
    }

    /** $count periods back, as DateTimeImmutable::modify() takes it. */
    public function back(int $count): string
    {
        return match ($this) {
            self::Hour => sprintf('-%d hours', $count),
            self::Day => sprintf('-%d days', $count),
            self::Week, self::SundayWeek => sprintf('-%d weeks', $count),
            self::Month => sprintf('-%d months', $count),
            self::Quarter => sprintf('-%d months', 3 * $count),
            self::Year => sprintf('-%d years', $count),
        };
    }

    /** One period, as DateTimeImmutable::modify() takes it. */
    public function step(): string
    {
        return match ($this) {
            self::Hour => '+1 hour',
            self::Day => '+1 day',
            self::Week, self::SundayWeek => '+1 week',
            self::Month => '+1 month',
            self::Quarter => '+3 months',
            self::Year => '+1 year',
        };
    }
}
