<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Byfareska\Crud\Security\Actor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The bundle's {@see DashboardPolicy}: everybody signed in arranges a layout of their own; somebody with
 * byfareska_crud.dashboard.share_role (none = nobody) stores theirs as the one for everybody, which is what the others
 * get until they change it.
 */
final readonly class RoleDashboardPolicy implements DashboardPolicy
{
    public function __construct(
        private Actor $actor,
        #[Autowire(param: 'byfareska_crud.dashboard.share_role')]
        private ?string $shareRole = null,
    ) {
    }

    public function fallbackOwners(string $board): array
    {
        return [null];
    }

    public function shareOwner(string $board): ?string
    {
        return null;
    }

    public function mayShare(string $board): bool
    {
        return $this->shareRole !== null && $this->actor->id() !== null && $this->actor->isGranted($this->shareRole);
    }

    public function mayEdit(string $board): bool
    {
        return $this->actor->id() !== null;
    }
}
