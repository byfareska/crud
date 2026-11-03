<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The record of an "own" resource for the one asking: what an OwnRecordResolver of the resource says, else their id.
 */
final readonly class OwnRecords
{
    /**
     * @param iterable<OwnRecordResolver> $resolvers
     */
    public function __construct(
        private CrudAccess $access,
        #[AutowireIterator(OwnRecordResolver::TAG)]
        private iterable $resolvers = [],
    ) {
    }

    /**
     * @throws AccessDeniedHttpException nobody is signed in, or the one signed in has no such record
     */
    public function idOf(AsCrud $crud): string
    {
        foreach ($this->resolvers as $resolver) {
            if (!in_array($resolver->resource(), [$crud->name, OwnRecordResolver::EVERY], true)) {
                continue;
            }
            $id = $resolver->ownId($crud);
            if ($id !== null) {
                return $id;
            }
        }

        return $this->access->ownId();
    }
}
