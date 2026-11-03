<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use Closure;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Wraps every use case the panel runs (UseCaseExecutor): a log of who did what, the tenant of the request set for the
 * domain, a transaction of its own, a command sent to a Messenger bus in place of running it. A service,
 * autoconfigured; by the priority of its tag, the first outermost.
 *
 *     public function handle(MapsToRequest $dto, object $request, Closure $next): mixed
 *     {
 *         $this->logger->info('Use case', ['request' => $request::class]);
 *
 *         return $next($request);
 *     }
 */
#[AutoconfigureTag(self::TAG)]
interface UseCaseMiddleware
{
    public const string TAG = 'byfareska_crud.use_case_middleware';

    /**
     * @param object $request what the DTO made (MapsToRequest::toRequest())
     * @param Closure(object): mixed $next the rest of the chain, then the use case
     *
     * @return mixed what the use case returns (or what is to stand for it)
     */
    public function handle(MapsToRequest $dto, object $request, Closure $next): mixed;
}
