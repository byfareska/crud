<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The CrudFormFillers of the resources.
 */
final readonly class CrudFormFillers
{
    /**
     * @param iterable<CrudFormFiller> $fillers
     */
    public function __construct(
        #[AutowireIterator(CrudFormFiller::TAG)]
        private iterable $fillers,
    ) {
    }

    /**
     * @return array<string, CrudFormFiller> of the resource, by name
     */
    public function of(string $resource): array
    {
        $fillers = [];
        foreach ($this->fillers as $filler) {
            if ($filler->resource() === $resource) {
                $fillers[$filler->name()] = $filler;
            }
        }

        return $fillers;
    }
}
