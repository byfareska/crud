<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The {@see DoctrineScope}s of the application, by the resource they narrow.
 */
final readonly class DoctrineScopes
{
    /**
     * @param iterable<DoctrineScope> $scopes
     */
    public function __construct(
        #[AutowireIterator(DoctrineScope::TAG)]
        private iterable $scopes = [],
    ) {
    }

    /** Whether any scope narrows the entities of the resource. */
    public function narrow(AsCrud $crud): bool
    {
        return $this->of($crud) !== [];
    }

    public function apply(DoctrineQuery $query): void
    {
        foreach ($this->of($query->crud) as $scope) {
            $scope->apply($query);
        }
    }

    /**
     * Only the scopes of every resource (DoctrineScope::EVERY): for the entities of a relation that have no resource
     * of their own (see DoctrineRelations).
     */
    public function applyEvery(DoctrineQuery $query): void
    {
        foreach ($this->scopes as $scope) {
            if ($scope->resource() === DoctrineScope::EVERY) {
                $scope->apply($query);
            }
        }
    }

    /**
     * @return list<DoctrineScope>
     */
    private function of(AsCrud $crud): array
    {
        $of = [];
        foreach ($this->scopes as $scope) {
            if (in_array($scope->resource(), [$crud->name, DoctrineScope::EVERY], true)) {
                $of[] = $scope;
            }
        }

        return $of;
    }
}
