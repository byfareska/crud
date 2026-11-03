<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use RuntimeException;

/**
 * The one performing the operation is not allowed to.
 */
final class AccessDeniedException extends RuntimeException
{
    public static function missingRole(string $role): self
    {
        return new self(sprintf('This needs the role "%s".', $role));
    }
}
