<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Changes every chart the ChartFactory makes, once it is made: the font of the design system, numbers in the format of
 * the locale, another palette, a plugin. A service, autoconfigured; by the priority of its tag. Which chart it is - of
 * a factory that names its charts (ChartFactory::named()) - is ChartFactory::nameOf($chart).
 *
 *     public function extend(Chart $chart): void
 *     {
 *         $legend = ['plugins' => ['legend' => ['position' => 'bottom']]];
 *         $chart->setOptions(array_replace_recursive($chart->getOptions(), $legend));
 *     }
 */
#[AutoconfigureTag(self::TAG)]
interface ChartExtension
{
    public const string TAG = 'byfareska_crud.chart_extension';

    /**
     * @param Chart $chart its type is $chart->getType() (Chart::TYPE_LINE, TYPE_BAR, TYPE_DOUGHNUT)
     */
    public function extend(Chart $chart): void;
}
