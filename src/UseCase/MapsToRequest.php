<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use Byfareska\Crud\Attribute\UseCase;
use ValueError;

/**
 * A DTO that knows how to become the request of the use case named by its {@see UseCase} attribute.
 */
interface MapsToRequest
{
    /**
     * @throws InvalidIdException when an id the DTO carries is not one; reported as "not found"
     * @throws UseCaseFailed when the DTO cannot be turned into a request at all
     * @throws ValueError for a value the request does not take (Enum::from()); reported as refused, 422
     */
    public function toRequest(): object;
}
