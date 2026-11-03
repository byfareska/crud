<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Dashboard\WidgetPlacement;

/**
 * A layout of a dashboard was stored (dispatched on Symfony's event dispatcher): moved, a widget added, removed or set
 * up - for an audit log, a cache of what is on the dashboards.
 */
final readonly class DashboardLayoutSaved
{
    /**
     * @param string $owner whose layout (the id of the one acting)
     * @param list<WidgetPlacement> $placements what it is now
     */
    public function __construct(
        public string $board,
        public string $owner,
        public array $placements,
    ) {
    }
}
