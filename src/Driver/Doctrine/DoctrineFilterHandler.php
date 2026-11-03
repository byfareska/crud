<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Symfony\Component\Form\FormBuilderInterface;

/**
 * Makes the field of one kind of {@see DoctrineFilter} and narrows the query of the list by its value: a service,
 * found by its class - the one the filter names in DoctrineFilter::handler(). Autoconfigured.
 */
interface DoctrineFilterHandler
{
    /** Added to every service implementing it (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.doctrine_filter_handler';

    /**
     * The field of the list form, named $name; one value (the toolbar binds the field as a whole).
     *
     * @param array<string, mixed> $options the label and "required" => false, for the field
     *
     * @return FormBuilderInterface<mixed>
     */
    public function field(DoctrineFilter $filter, string $name, array $options): FormBuilderInterface;

    /**
     * Narrows the query by the value of the field; not called while it is empty (null, "", []).
     */
    public function apply(DoctrineFilter $filter, DoctrineListQuery $query, string $name, mixed $value): void;
}
