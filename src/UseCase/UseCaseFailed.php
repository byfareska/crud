<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use Byfareska\Crud\Resource\CrudFailure;

/**
 * A use case refused the request for a reason the caller can act on - what an exception listed in the DTO's
 * #[UseCase(errors: ...)] becomes (see {@see UseCaseExecutor}).
 */
final class UseCaseFailed extends CrudFailure
{
}
