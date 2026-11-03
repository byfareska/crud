<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;
use Byfareska\Crud\UseCase\Failure;
use Byfareska\Crud\UseCase\MapsToRequest;
use Byfareska\Crud\UseCase\UseCaseExecutor;
use Throwable;

/**
 * Names the use case a DTO is the input of. Put on a {@see MapsToRequest} DTO; {@see UseCaseExecutor} does the rest,
 * whether the DTO arrived over REST, as MCP tool arguments or from a form in the panel.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class UseCase
{
    /**
     * @param class-string $class an invokable Query or Command
     * @param array<class-string<Throwable>, Failure> $errors what the exceptions the use case throws mean to the caller
     */
    public function __construct(
        public string $class,
        public array $errors = [],
    ) {
    }
}
