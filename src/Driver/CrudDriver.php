<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\UseCase\Page;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Reads and writes the records of the resources whose {@see CrudSource} names it. The pages, the live list and form,
 * the palette and the details all go through a driver, so they do not know whether a record is an entity, the
 * result of a use case or a row of an API.
 *
 * Ids are text, as in an address. Values of records are arrays by field: what the list shows, the details print
 * and the edit form starts with.
 */
#[AutoconfigureTag(self::TAG)]
interface CrudDriver
{
    public const string TAG = 'byfareska_crud.driver';

    /** What a CrudSource names it by: "doctrine", "dto", ... */
    public function name(): string;

    /**
     * The form of the list, named "filters" and not submitted. Its fields "page", "perPage", "fields" and "sort" are
     * driven by the list (each only when the form has it): the page, the number of items on it, what an item has to
     * carry (the columns, "id" and AsCrud::$fields) and the order - a multiple choice of field names, each also with
     * a minus in front for descending, whose "empty_data" is the order of a list nobody sorted. Every other field
     * is a filter of the toolbar.
     *
     * @return FormInterface<mixed>
     */
    public function listForm(AsCrud $crud): FormInterface;

    /**
     * One page of the records the submitted (and valid) list form asks for. An item has its "id" and the fields
     * asked for.
     *
     * @param FormInterface<mixed> $form
     *
     * @throws CrudFailure
     */
    public function list(AsCrud $crud, FormInterface $form): Page;

    /**
     * A record by field, as the details page and the preview drawer show it.
     *
     * @return array<string, mixed>
     *
     * @throws CrudFailure 404 when there is none
     */
    public function read(AsCrud $crud, string $id): array;

    /**
     * @throws CrudFailure
     */
    public function delete(AsCrud $crud, string $id): void;

    /**
     * The add form ($id null) or the edit form of a record, carrying its current values; not submitted. The edit
     * form has no "id" field: the record is the one the page was opened for.
     *
     * @param array<string, mixed> $with what a new record starts with, from the address (?with[parent]=...): plain
     *     values by field; a driver leaves out what is no field of its own
     *
     * @return FormBuilderInterface<mixed>
     *
     * @throws CrudFailure 404 when the record is gone
     */
    public function form(AsCrud $crud, ?string $id, array $with = []): FormBuilderInterface;

    /**
     * Saves what the submitted, valid form says.
     *
     * @param FormInterface<mixed> $form
     *
     * @return string|null the id of the record (of a new one: if the driver can tell)
     *
     * @throws CrudFailure shown at its field, or above the form
     */
    public function save(AsCrud $crud, FormInterface $form, ?string $id): ?string;
}
