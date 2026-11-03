<?php

declare(strict_types=1);

namespace Byfareska\Crud\Security;

use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user's getId() when it has one (an int, a string, a Uuid...), else its identifier.
 */
final readonly class DefaultUserIdMapper implements UserIdMapper
{
    public function idOf(UserInterface $user): int|string|Stringable
    {
        $id = method_exists($user, 'getId') ? $user->getId() : null;

        if (is_int($id) || $id instanceof Stringable || (is_string($id) && $id !== '')) {
            return $id;
        }

        return $user->getUserIdentifier();
    }
}
