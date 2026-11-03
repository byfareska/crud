<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Navigation\NavigationItem;
use Byfareska\Crud\Navigation\NavigationSource;

/**
 * The list of every #[AsCrud] resource that names a "section" (the page of one's own record, for an "own" one), as a
 * page of the menu of the panel of its route scope - as the resource is in the request, and only for somebody who may
 * open it (CrudAccess: its role asked about the resource, its operation not refused).
 */
final readonly class CrudNavigationSource implements NavigationSource
{
    public function __construct(
        private CrudRegistry $registry,
        private CrudAccess $access,
    ) {
    }

    public function items(): iterable
    {
        foreach ($this->registry->all() as $crud) {
            $opens = $crud->own
                ? $crud->can(CrudOperation::Read) || $crud->can(CrudOperation::Update)
                : $crud->isListed() && $crud->can(CrudOperation::List);
            if ($crud->section !== null && $opens && $this->access->isGranted($crud)) {
                yield new NavigationItem(
                    section: $crud->section,
                    routeName: $crud->own ? $crud->ownPage() : $crud->route('list'),
                    label: $crud->key('title'),
                    icon: $crud->icon,
                    priority: $crud->priority,
                    scope: $crud->scope,
                    resource: $crud->name,
                );
            }
        }
    }
}
