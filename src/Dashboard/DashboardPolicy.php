<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * Who may do what to a dashboard, and whose layout one without a layout of their own gets. The bundle's is
 * {@see RoleDashboardPolicy}: everybody signed in arranges their own, somebody with byfareska_crud.dashboard.share_role
 * stores theirs for everybody, and the one for everybody is the only fallback. Alias this interface for more:
 *
 * - a default layout per team or per role: fallbackOwners() ['team:' . $team, null] - the team's, else everybody's -
 *   and shareOwner() 'team:' . $team for what a team lead shares;
 * - a dashboard nobody rearranges (a kiosk, a fixed layout for a role): mayEdit() false.
 *
 * An owner is text, kept next to the ids of the users (DashboardLayouts): give the owners of your own a prefix of
 * their own ("team:5") so no user id is one.
 */
interface DashboardPolicy
{
    /**
     * Whose layouts the one acting gets, in order, when they have none of their own; null = the one for everybody.
     *
     * @return list<string|null>
     */
    public function fallbackOwners(string $board): array;

    /** Whose layout share() stores; null = the one for everybody. */
    public function shareOwner(string $board): ?string;

    /** Whether the one acting may store their layout as that of shareOwner(). */
    public function mayShare(string $board): bool;

    /** Whether the one acting may move, add, remove, set up and reset widgets of their own layout. */
    public function mayEdit(string $board): bool;
}
