<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * A widget of the gallery of a dashboard.
 */
final readonly class WidgetView
{
    /**
     * @param array<string, array{w: int, h: int}> $sizes what it can be added in, by name (s, m, l, xl, default)
     * @param string $size the one chosen at first
     * @param int $placed how many times it is on the dashboard already
     */
    public function __construct(
        public DashboardWidget $widget,
        public array $sizes,
        public string $size,
        public int $placed,
    ) {
    }
}
