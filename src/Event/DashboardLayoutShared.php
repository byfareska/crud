<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Dashboard\WidgetPlacement;

/**
 * Somebody stored their layout as that of others (dispatched on Symfony's event dispatcher) - DashboardPolicy::
 * shareOwner(): everybody (null) or an owner of the application's own ("team:5").
 */
final readonly class DashboardLayoutShared
{
    /**
     * @param string $by the id of the one who shared it
     * @param list<WidgetPlacement> $placements what was stored, without the personal settings
     */
    public function __construct(
        public string $board,
        public ?string $owner,
        public string $by,
        public array $placements,
    ) {
    }
}
