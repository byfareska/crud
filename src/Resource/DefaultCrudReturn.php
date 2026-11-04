<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where the pages of a record lead back to (their "back" button, the page after a save and after a delete): the list
 * of the resource, or - for one listed in another resource's list (CrudListedIn) - that list filtered by the record;
 * for one's own record (AsCrud::$own) its page. After a delete the list is not filtered by the record that is gone.
 */
final readonly class DefaultCrudReturn implements CrudSaveReturn
{
    public function __construct(
        private CrudRegistry $registry,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param string|null $id the record; null = none (a new one not saved): the list without the filter
     */
    public function url(AsCrud $crud, ?string $id = null): string
    {
        if ($crud->own) {
            return $this->urls->generate($crud->ownPage());
        }

        $listedIn = $crud->listedIn;
        if (!$listedIn instanceof CrudListedIn) {
            return $this->urls->generate($crud->route('list'));
        }

        return $this->urls->generate(
            $this->registry->get($listedIn->resource)->route('list'),
            $id === null || $id === '' ? [] : ['filters' => [$listedIn->filter => $id]],
        );
    }

    public function afterSave(AsCrud $crud, ?string $id, bool $created): string
    {
        return $this->url($crud, $id);
    }

    /**
     * "Save and add another": the add form (if the resource still adds); "save and keep editing": the edit form of
     * the record (if it is known and edited); "save and view": its details (if it is known and read) - else the way
     * back (afterSave()).
     */
    public function afterSaveTo(AsCrud $crud, ?string $id, bool $created, AfterSave $then): string
    {
        return match (true) {
            $then === AfterSave::New && $crud->can(CrudOperation::Create)
                => $this->urls->generate($crud->route('new')),
            $then === AfterSave::Edit && ($id !== null || $crud->own) && $crud->can(CrudOperation::Update)
                => $this->urls->generate($crud->route('edit'), $crud->recordParameters($id)),
            $then === AfterSave::Show && ($id !== null || $crud->own) && $crud->can(CrudOperation::Read)
                => $this->urls->generate($crud->route('show'), $crud->recordParameters($id)),
            default => $this->afterSave($crud, $id, $created),
        };
    }

    public function afterDelete(AsCrud $crud, string $id): string
    {
        return $this->url($crud);
    }
}
