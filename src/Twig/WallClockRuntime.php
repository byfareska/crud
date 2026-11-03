<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Byfareska\Crud\Chart\ChartTimezone;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * The filter crud_wall_clock: a moment as the clock of the panel shows it, for a translation that formats it
 * ("{at, date, medium}, {at, time, short}"). ICU formats a date in the default zone of PHP whatever zone it has, so a
 * time written in UTC would be shown as the server's; this gives the time of the zone the panel shows times in -
 * ChartTimezone: the zone of Twig, or of the user's profile when it is aliased so - as a moment of the default zone
 * with that time on its clock. A day ("2026-10-19", no time) is that day, in whatever zone.
 */
final readonly class WallClockRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ChartTimezone $timezone,
    ) {
    }

    public function of(DateTimeInterface|string $value): DateTimeImmutable
    {
        $default = new DateTimeZone(date_default_timezone_get());
        if (is_string($value)) {
            // A day: its midnight where ICU formats it, not a day earlier or later.
            return new DateTimeImmutable($value, $default);
        }

        $shown = DateTimeImmutable::createFromInterface($value)->setTimezone($this->timezone->zone());

        return new DateTimeImmutable($shown->format('Y-m-d H:i:s.u'), $default);
    }
}
