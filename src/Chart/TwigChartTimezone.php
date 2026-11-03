<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use DateTimeZone;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * The zone Twig shows dates in (twig.date.timezone): the default {@see ChartTimezone}.
 */
final readonly class TwigChartTimezone implements ChartTimezone
{
    public function __construct(private Environment $twig)
    {
    }

    public function zone(): DateTimeZone
    {
        return $this->twig->getExtension(CoreExtension::class)->getTimezone();
    }
}
