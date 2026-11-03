<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * A widget placed on a dashboard, with what the grid needs to know of it.
 */
final readonly class PlacementView
{
    /**
     * @param array<string, string|list<string>> $settings
     */
    public function __construct(
        public string $id,
        public DashboardWidget $widget,
        public int $x,
        public int $y,
        public int $w,
        public int $h,
        public array $settings,
    ) {
    }
}
