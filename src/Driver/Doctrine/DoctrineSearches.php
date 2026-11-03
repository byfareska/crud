<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * The {@see DoctrineSearch} of a source: the one it names (searchWith), else the application's.
 */
final readonly class DoctrineSearches
{
    public function __construct(
        private DoctrineSearch $default,
        #[AutowireLocator(DoctrineSearch::TAG)]
        private ContainerInterface $searches,
    ) {
    }

    /**
     * @throws LogicException the search it names is no service
     */
    public function of(?DoctrineSource $source): DoctrineSearch
    {
        $id = $source?->searchWith;
        if ($id === null) {
            return $this->default;
        }
        if (!$this->searches->has($id)) {
            throw new LogicException(sprintf(
                'The entities of %s are searched with %s, which is no service tagged "%s" - is it autoconfigured?',
                $source->entity,
                $id,
                DoctrineSearch::TAG,
            ));
        }
        $search = $this->searches->get($id);
        assert($search instanceof DoctrineSearch);

        return $search;
    }
}
