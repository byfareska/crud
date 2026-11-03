<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Throwable;

/**
 * What an exception of a use case means to the one using the panel, for every use case at once: what the errors of
 * #[UseCase] say of one DTO ("NotFound" of the domain → 404, "Conflict" → 409). Asked after them, in the priority
 * of its tag; null = not one it knows. A service, autoconfigured.
 */
#[AutoconfigureTag(self::TAG)]
interface UseCaseErrorMapper
{
    public const string TAG = 'byfareska_crud.use_case_error_mapper';

    public function map(MapsToRequest $dto, Throwable $exception): ?UseCaseFailed;
}
