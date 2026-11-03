<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use Byfareska\Crud\Attribute\UseCase;
use LogicException;

/**
 * Which use case a DTO runs, and the failures it reports: by default its #[UseCase] (AttributeUseCaseResolver). Alias
 * it to say it otherwise - a DTO of a library that has no attribute, another use case for a tenant or a version of
 * the API. A UseCaseMiddleware that has to know the use case (a transaction for a Command, a bus for an async one)
 * asks it too.
 */
interface UseCaseResolver
{
    /**
     * @throws LogicException the DTO names no use case
     */
    public function of(MapsToRequest $dto): UseCase;
}
