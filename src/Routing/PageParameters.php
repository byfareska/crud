<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The parameters of the address of the panel page being answered that are the application's: those of the prefix of
 * its route import (/{tenant}/admin) and of the path of a resource (/categories/{category}/products) - not those of
 * the bundle (the resource, the id, the page of the list). Known on the page itself and in the live requests of its
 * components (/_components/..., by the address of the page they come from), so a DoctrineScope, a RecordGuard, a
 * CrudDefinitionExtension or a listener can go by them:
 *
 *     $category = $this->pageParameters->get('category');
 *
 * They are also the parameters of the routing context: every address the bundle or a template makes has them, as
 * Symfony does with "_locale". What they are is up to the one asking - an address can say anything: whether the one
 * looking may see that tenant, that category, is for the application to check (a DoctrineScope, a guard).
 * Set by {@see PageParametersListener}.
 */
final class PageParameters implements ResetInterface
{
    /** @var array<string, string> */
    private array $parameters = [];

    /**
     * @param array<string, string> $parameters
     */
    public function set(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function get(string $name): ?string
    {
        return $this->parameters[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * Runs $work with these parameters, then gives back those there were - what a message handler of a queue does
     * with the parameters a job carried (BulkSelection::$parameters): its DoctrineScopes narrow by the tenant of the
     * page the job was sent from, not by none.
     *
     * @template T
     *
     * @param array<string, string> $parameters
     * @param \Closure(): T $work
     *
     * @return T
     */
    public function within(array $parameters, \Closure $work): mixed
    {
        $before = $this->parameters;
        $this->parameters = $parameters;
        try {
            return $work();
        } finally {
            $this->parameters = $before;
        }
    }

    public function reset(): void
    {
        $this->parameters = [];
    }
}
