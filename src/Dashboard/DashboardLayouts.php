<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * Where the layouts of the dashboards are kept: one per owner and dashboard, and one with no owner - stored for
 * everybody who has none of their own. The bundle has {@see Storage\DoctrineDashboardLayouts} (a table) and
 * {@see Storage\CacheDashboardLayouts} (a cache pool, no schema); byfareska_crud.dashboard.storage picks, or an
 * application aliases this interface to a service of its own.
 */
interface DashboardLayouts
{
    /**
     * @param string|null $owner the id of the one it belongs to (Actor::id()); null = the one for everybody
     *
     * @return list<WidgetPlacement>|null as stored (widgets that are gone may be among them); null = none
     */
    public function find(?string $owner, string $board): ?array;

    /**
     * @param list<WidgetPlacement> $placements
     */
    public function save(?string $owner, string $board, array $placements): void;

    public function delete(?string $owner, string $board): void;
}
