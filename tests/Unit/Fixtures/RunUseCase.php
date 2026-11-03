<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\UseCase\Command;

/**
 * Stands for a use case in a test: the executor is given what runs in its place.
 */
final readonly class RunUseCase implements Command
{
    public function __invoke(object $request): mixed
    {
        return null;
    }
}
