<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Security\Actor;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Whether the pages of a resource are for the one asking: the "role" of its #[AsCrud] (asked with the resource as the
 * subject) and the role of its panel (byfareska_crud.scopes.<scope>.role) - and, given an operation, that the request
 * does not refuse it (AsCrud::$roles, AsCrud::refusing()). Asked by every way to its records - its pages, the live
 * list and form, the command palette - so none of them depends on access_control, which does not see them all
 * (/_components/...).
 */
final readonly class DefaultCrudAccess implements CrudAccess
{
    public function __construct(
        private Actor $actor,
        private Scopes $scopes,
    ) {
    }

    /**
     * @param CrudOperation|null $operation what is to be done; null = only whether the resource is for the one asking
     */
    public function isGranted(AsCrud $crud, ?CrudOperation $operation = null): bool
    {
        return $this->scopes->isGrantedIn($crud->scope, $crud->role, $crud)
            && (!$operation instanceof CrudOperation || !$crud->isRefused($operation));
    }

    /**
     * The record of an "own" resource: the id of the one signed in.
     *
     * @throws AccessDeniedHttpException nobody is signed in
     */
    public function ownId(): string
    {
        return $this->actor->id()
            ?? throw new AccessDeniedHttpException('Only somebody signed in has a record of their own.');
    }

    /**
     * Whether a page of #[AsCrudPage] is for the one asking, besides its resource: its role, asked about the resource.
     *
     * @throws AccessDeniedHttpException
     */
    public function assertPageGranted(AsCrudPage $page, AsCrud $crud): void
    {
        if ($page->role !== null && !$this->actor->isGranted($page->role, $crud)) {
            throw new AccessDeniedHttpException(sprintf(
                'The page "%s" needs the role "%s".',
                $page->name,
                $page->role,
            ));
        }
    }

    /**
     * @param CrudOperation|null $operation what is to be done; null = only whether the resource is for the one asking
     *
     * @throws AccessDeniedHttpException
     */
    public function assertGranted(AsCrud $crud, ?CrudOperation $operation = null): void
    {
        if ($operation instanceof CrudOperation && $this->isGranted($crud) && $crud->isRefused($operation)) {
            throw new AccessDeniedHttpException(sprintf(
                'The section "%s" does not %s for the one asking (%s).',
                $crud->name,
                $operation->value,
                $crud->roleOf($operation) ?? 'refused by an extension',
            ));
        }
        if (!$this->isGranted($crud)) {
            throw new AccessDeniedHttpException(sprintf(
                'The section "%s" needs the role "%s" and that of the panel "%s" ("%s").',
                $crud->name,
                (string) $crud->role,
                $crud->scope,
                (string) $this->scopes->get($crud->scope)->role,
            ));
        }
    }
}
