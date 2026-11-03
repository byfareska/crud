<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Chart;

use Byfareska\Crud\Chart\ChartExtension;
use Byfareska\Crud\Chart\ChartFactory;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilder;
use Symfony\UX\Chartjs\Model\Chart;

final class ChartExtensionTest extends TestCase
{
    public function testAnExtensionChangesEveryChart(): void
    {
        $bottomLegend = new class implements ChartExtension {
            public function extend(Chart $chart): void
            {
                $legend = ['plugins' => ['legend' => ['position' => 'bottom']]];
                $chart->setOptions(array_replace_recursive($chart->getOptions(), $legend));
            }
        };
        $charts = new ChartFactory(new ChartBuilder(), [$bottomLegend]);

        $line = $charts->line(['Mon'], [['label' => 'Orders', 'data' => [3], 'color' => '#000']]);
        $ring = $charts->ring(['Done'], [1], ['#000']);

        foreach ([$line, $ring] as $chart) {
            $plugins = $chart->getOptions()['plugins'] ?? null;
            $legend = is_array($plugins) ? $plugins['legend'] ?? null : null;
            self::assertSame('bottom', is_array($legend) ? $legend['position'] ?? null : null);
        }
    }

    public function testAnExtensionKnowsWhichChartItIs(): void
    {
        $seen = new class implements ChartExtension {
            /** @var list<string|null> */
            public array $names = [];

            public function extend(Chart $chart): void
            {
                $this->names[] = ChartFactory::nameOf($chart);
            }
        };
        $charts = new ChartFactory(new ChartBuilder(), [$seen]);

        $sales = $charts->named('sales')->line(['Mon'], [['label' => 'Orders', 'data' => [3], 'color' => '#000']]);
        $charts->ring(['Done'], [1], ['#000']);

        self::assertSame(['sales', null], $seen->names);
        self::assertSame('sales', $sales->getAttributes()[ChartFactory::NAME_ATTRIBUTE] ?? null, 'In the browser too.');
    }

    public function testAChartOfAnyTypeIsMadeLikeTheOthers(): void
    {
        $charts = new ChartFactory(new ChartBuilder());

        $radar = $charts->custom('radar', ['Speed', 'Price'], [['data' => [3, 5], 'borderColor' => 'var(--violet)']], [
            'plugins' => ['legend' => ['display' => true]],
        ]);

        self::assertSame('radar', $radar->getType());
        self::assertSame('chart-theme', $radar->getAttributes()['data-controller'] ?? null);
        $options = $radar->getOptions();
        self::assertSame(
            ['display' => true],
            (array) (((array) ($options['plugins'] ?? []))['legend'] ?? []),
            'The options given over the base ones.',
        );
        self::assertFalse($options['maintainAspectRatio'] ?? null);
    }
}
