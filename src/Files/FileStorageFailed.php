<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use RuntimeException;

/**
 * A storage could not do what it was asked: the disk is full, the server did not answer.
 */
final class FileStorageFailed extends RuntimeException
{
}
