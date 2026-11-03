<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudPage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Whether the pages of a resource are for the one asking. Asked by every way to its records - its pages, the live
 * list and form, the command palette, the menu - so none of them depends on access_control, which does not see them
 * all (/_components/...). The bundle's is {@see DefaultCrudAccess}: the role of the resource and of its panel, and the
 * operations the request does not refuse. Decorate it (#[AsDecorator(CrudAccess::class)]) to add a rule of the
 * application's own - a tenant, a licence - to every way at once.
 */
interface CrudAccess
{
    /**
     * @param CrudOperation|null $operation what is to be done; null = only whether the resource is for the one asking
     */
    public function isGranted(AsCrud $crud, ?CrudOperation $operation = null): bool;

    /**
     * @param CrudOperation|null $operation what is to be done; null = only whether the resource is for the one asking
     *
     * @throws AccessDeniedHttpException
     */
    public function assertGranted(AsCrud $crud, ?CrudOperation $operation = null): void;

    /**
     * Whether a page of #[AsCrudPage] is for the one asking, besides its resource.
     *
     * @throws AccessDeniedHttpException
     */
    public function assertPageGranted(AsCrudPage $page, AsCrud $crud): void;

    /**
     * The record of an "own" resource: the id of the one signed in.
     *
     * @throws AccessDeniedHttpException nobody is signed in
     */
    public function ownId(): string;
}
