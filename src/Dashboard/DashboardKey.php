<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * What the layouts of a dashboard are kept under (DashboardLayouts): the user's own, and the one shared. The bundle's
 * is {@see ParametersDashboardKey}: the panel and the parameters of its address - a panel per shop
 * (/{shop}/admin) keeps the layouts of each shop apart. Alias the interface for another context: the tenant of the
 * user, a team, a dashboard on the page of a record.
 */
interface DashboardKey
{
    /**
     * @param string $board the dashboard: the name of its panel
     *
     * @return string at most 64 characters (the column of the doctrine storage)
     */
    public function of(string $board): string;
}
