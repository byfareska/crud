<?php

declare(strict_types=1);

namespace Byfareska\Crud\Chart;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Charts of the dashboard with the looks of Apple's widgets: no grid but a faint horizontal line, rounded bars, soft
 * curves with a gradient under them, no legend for a single series, the size given by the card (no aspect ratio).
 *
 * Colours are never written here: a colour is a CSS property, "var(--violet)" ("gradient(var(--violet))" for
 * the area under a line), which the `chart-theme` controller resolves in the browser - again when the theme changes.
 */
final readonly class ChartFactory
{
    /** The attribute of the canvas that names the chart (named()). */
    public const string NAME_ATTRIBUTE = 'data-chart-name';

    /**
     * @param iterable<ChartExtension> $extensions
     * @param string|null $name of the charts it makes - see named()
     */
    public function __construct(
        private ChartBuilderInterface $builder,
        #[AutowireIterator(ChartExtension::TAG)]
        private iterable $extensions = [],
        private ?string $name = null,
    ) {
    }

    /**
     * The same factory, naming the charts it makes - "sales", "stock.warehouse_5" - so a ChartExtension (nameOf()), a
     * formatter of the browser and a listener of "chart-theme:options" (the attribute data-chart-name of the canvas)
     * know which chart it is: `$charts->named('sales')->line(...)`.
     */
    public function named(string $name): self
    {
        return new self($this->builder, $this->extensions, $name);
    }

    /** The name of a chart made by a named() factory; null for one of no name. */
    public static function nameOf(Chart $chart): ?string
    {
        $name = $chart->getAttributes()[self::NAME_ATTRIBUTE] ?? null;

        return is_string($name) ? $name : null;
    }

    /**
     * @param list<string> $labels
     * @param list<array{label: string, data: list<int|float>, color: string}> $series
     */
    public function line(array $labels, array $series, bool $area = true): Chart
    {
        $datasets = [];
        foreach ($series as $index => $one) {
            $datasets[] = [
                'label' => $one['label'],
                'data' => $one['data'],
                'borderColor' => $one['color'],
                // The first series gets the area, the others are lines over it.
                'backgroundColor' => $area && $index === 0 ? 'gradient(' . $one['color'] . ')' : $one['color'],
                'fill' => $area && $index === 0 ? 'origin' : false,
            ];
        }

        return $this->make(Chart::TYPE_LINE, $labels, $datasets, $this->axes(legend: count($series) > 1));
    }

    /**
     * A small curve with nothing around it: no axes, no tooltip, no points.
     *
     * @param list<int|float> $values
     */
    public function sparkline(array $values, string $color): Chart
    {
        $options = $this->merge($this->base(), [
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false, 'beginAtZero' => true]],
            'plugins' => ['tooltip' => ['enabled' => false]],
            'layout' => ['padding' => 2],
            'events' => [],
        ]);

        return $this->make(Chart::TYPE_LINE, array_map(strval(...), array_keys($values)), [[
            'data' => array_values($values),
            'borderColor' => $color,
            'backgroundColor' => 'gradient(' . $color . ')',
            'fill' => 'origin',
        ]], $options);
    }

    /**
     * @param list<string> $labels
     * @param list<array{label: string, data: list<int|float>, color: string}> $series
     */
    public function bar(array $labels, array $series, bool $stacked = false): Chart
    {
        $options = $this->axes(legend: count($series) > 1);
        if ($stacked) {
            $options = $this->merge($options, ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]]);
        }

        return $this->make(Chart::TYPE_BAR, $labels, array_map(static fn (array $one): array => [
            'label' => $one['label'],
            'data' => $one['data'],
            'backgroundColor' => $one['color'],
            'maxBarThickness' => 22,
        ], $series), $options);
    }

    /**
     * Bars lying down, one per label: a ranking.
     *
     * @param list<string> $labels
     * @param list<int|float> $values
     */
    public function hbar(array $labels, array $values, string $color, string $label = ''): Chart
    {
        $options = $this->axes(legend: false);
        $options['indexAxis'] = 'y';
        $options['scales'] = [
            'x' => ['display' => false, 'beginAtZero' => true, 'grid' => ['display' => false]],
            'y' => ['grid' => ['display' => false], 'border' => ['display' => false], 'ticks' => ['autoSkip' => false]],
        ];

        return $this->make(Chart::TYPE_BAR, $labels, [[
            'label' => $label,
            'data' => $values,
            'backgroundColor' => $color,
            'maxBarThickness' => 16,
        ]], $options);
    }

    /**
     * @param list<string> $labels
     * @param list<int|float> $values
     * @param list<string> $colors one per label
     */
    public function doughnut(array $labels, array $values, array $colors): Chart
    {
        return $this->round($labels, $values, $colors, '62%');
    }

    /**
     * A thin doughnut, like the rings of Apple's activity: for a share of a whole.
     *
     * @param list<string> $labels
     * @param list<int|float> $values
     * @param list<string> $colors one per label
     */
    public function ring(array $labels, array $values, array $colors): Chart
    {
        return $this->round($labels, $values, $colors, '78%');
    }

    /**
     * @param list<string> $labels
     * @param list<int|float> $values
     * @param list<string> $colors
     */
    private function round(array $labels, array $values, array $colors, string $cutout): Chart
    {
        $options = $this->base();
        $options['cutout'] = $cutout;

        return $this->make(Chart::TYPE_DOUGHNUT, $labels, [[
            'data' => $values,
            'backgroundColor' => $colors,
            'borderWidth' => 0,
            'borderRadius' => 6,
            'spacing' => 2,
            'hoverOffset' => 4,
        ]], $options);
    }

    /**
     * A chart of any type Chart.js has - pie, radar, scatter, polarArea, a bar and a line on two axes - in the colours
     * of the page like the others: its datasets may name colours as CSS properties ("var(--violet)",
     * "gradient(var(--teal))"), the options are added to the base ones of every chart (no legend, rounded bars, thin
     * lines), the ChartExtensions see it too.
     *
     * @param list<string> $labels
     * @param list<array<string, mixed>> $datasets
     * @param array<string, mixed> $options
     * @param bool $axes whether it has the x and y axes of the others (in the colours of the text)
     */
    public function custom(string $type, array $labels, array $datasets, array $options = [], bool $axes = false): Chart
    {
        $base = $axes ? $this->merge($this->base(), $this->axes(false)) : $this->base();

        return $this->make($type, $labels, $datasets, $this->merge($base, $options));
    }

    /**
     * @return array<string, mixed>
     */
    private function base(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'animation' => ['duration' => 400],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['displayColors' => false, 'cornerRadius' => 10, 'padding' => 10],
            ],
            'elements' => [
                'line' => ['tension' => .35, 'borderWidth' => 2, 'borderCapStyle' => 'round'],
                'point' => ['radius' => 0, 'hoverRadius' => 4, 'hitRadius' => 12],
                'bar' => ['borderRadius' => 4, 'borderSkipped' => false],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function axes(bool $legend): array
    {
        return $this->merge($this->base(), [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => [
                'display' => $legend,
                'position' => 'bottom',
                'labels' => [
                    'usePointStyle' => true,
                    'pointStyle' => 'circle',
                    'boxWidth' => 6,
                    'boxHeight' => 6,
                    'padding' => 12,
                ],
            ]],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'border' => ['display' => false],
                    'ticks' => ['maxRotation' => 0, 'autoSkipPadding' => 12],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'border' => ['display' => false],
                    'ticks' => ['precision' => 0, 'maxTicksLimit' => 4],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $with
     *
     * @return array<string, mixed>
     */
    private function merge(array $options, array $with): array
    {
        /** @var array<string, mixed> */
        return array_replace_recursive($options, $with);
    }

    /**
     * @param list<string> $labels
     * @param list<array<string, mixed>> $datasets
     * @param array<string, mixed> $options
     */
    private function make(string $type, array $labels, array $datasets, array $options): Chart
    {
        $chart = $this->builder->createChart($type)
            ->setData(['labels' => $labels, 'datasets' => $datasets])
            ->setOptions($options)
            // A second controller on the canvas, connected before the chart's own: it resolves the colours.
            ->setAttributes(array_filter([
                'data-controller' => 'chart-theme',
                'data-action' => implode(' ', [
                    'chartjs:pre-connect->chart-theme#preConnect',
                    'chartjs:connect->chart-theme#connected',
                    'chartjs:view-value-change->chart-theme#change',
                ]),
                self::NAME_ATTRIBUTE => $this->name,
            ], static fn (?string $value): bool => $value !== null));
        // What the application makes of every chart: its font, its formats, a plugin.
        foreach ($this->extensions as $extension) {
            $extension->extend($chart);
        }

        return $chart;
    }
}
