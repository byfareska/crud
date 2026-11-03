<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use DateTimeZone;

/**
 * The time zone the periods of a chart are in - by default the one Twig shows dates in (TwigChartTimezone). Alias it
 * to one of your own for the zone of the one signed in (their profile), without changing every date of Twig.
 */
interface ChartTimezone
{
    public function zone(): DateTimeZone;
}
