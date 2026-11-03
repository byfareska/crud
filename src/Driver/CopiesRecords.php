<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver;

use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A driver that starts an add form as a copy of a record (the page to add one with ?from=<id>, DuplicateAction): what
 * the record has for the fields the form still has, enabled and mapped - after the CrudFormExtensions and the Whens.
 * The record is read within its scopes, by somebody its guards let read it (the form checks that before).
 */
interface CopiesRecords
{
    /**
     * @param FormBuilderInterface<mixed> $builder the add form, as the extensions left it
     * @param list<string> $fields the fields of the form to copy
     *
     * @throws CrudFailure 404 when the record is not there
     */
    public function copy(AsCrud $crud, string $id, FormBuilderInterface $builder, array $fields): void;
}
