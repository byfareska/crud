<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use InvalidArgumentException;

/**
 * An id that cannot be one (a typo in an address). Thrown by MapsToRequest::toRequest(); the executor reports it
 * as "not found", because something that is not an id names nothing that exists.
 */
class InvalidIdException extends InvalidArgumentException
{
}
