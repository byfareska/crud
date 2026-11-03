<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * A dashboard as the page draws it: the widgets placed on it and the gallery of those that can be added.
 */
final readonly class DashboardView
{
    /**
     * @param list<PlacementView> $placements
     * @param list<WidgetView> $widgets the gallery
     * @param LayoutSource $source whose layout it is
     * @param bool $mayShare whether the one looking may store it for others (DashboardPolicy)
     * @param bool $editable whether the one looking may rearrange it (DashboardPolicy)
     * @param int $columns of the grid
     */
    public function __construct(
        public string $board,
        public array $placements,
        public array $widgets,
        public LayoutSource $source,
        public bool $mayShare,
        public bool $editable = true,
        public int $columns = WidgetSize::COLUMNS,
    ) {
    }
}
