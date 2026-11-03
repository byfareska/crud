<?php

declare(strict_types=1);

namespace Byfareska\Crud\Security;

use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Which value tells a signed-in user apart: an int, a string, a Uuid... {@see SecurityActor} turns it into text with
 * the IdMapper, the same way as the ids of records. The bundle's own is {@see DefaultUserIdMapper}; an application
 * aliases this interface to its service to tell its users apart otherwise.
 */
interface UserIdMapper
{
    public function idOf(UserInterface $user): int|string|Stringable;
}
