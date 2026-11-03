<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Filter\Compared;
use Byfareska\Crud\Resource\CrudListQuery;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\FormInterface;

/**
 * The query of the list of a {@see DoctrineSource} while it is built: what a {@see DoctrineScope}, a
 * {@see DoctrineFilterHandler}, a {@see DoctrineSortHandler} and a {@see DoctrineListExtension} add to. The entity is
 * "e".
 */
final class DoctrineListQuery extends DoctrineQuery
{
    /**
     * @param ClassMetadata<object> $metadata of the entity
     * @param FormInterface<mixed> $form the submitted list form: the filters, the order, the page
     */
    public function __construct(
        AsCrud $crud,
        QueryBuilder $query,
        ClassMetadata $metadata,
        public readonly FormInterface $form,
    ) {
        parent::__construct($crud, $query, $metadata);
    }

    /**
     * Whether the one looking filters the list: a filter of the form (the search too) has a value - what a
     * DoctrineCount goes by (an estimate of the whole table only when nothing narrows it).
     */
    public function filtered(): bool
    {
        return array_any(
            $this->form->all(),
            fn(FormInterface $field, $name): bool => !in_array($name, CrudListQuery::CONTROLLED, true)
                && !Compared::isEmpty($field->getData()),
        );
    }
}
