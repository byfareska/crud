<?php

declare(strict_types=1);

namespace Byfareska\Crud\Page;

use Byfareska\Crud\Resource\RecordScreen;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Opens a page of a resource - one of the bundle's, or one of the application's taking a {@see CrudContext}: checks
 * that the one asking may see the resource and do what the page is for, takes the record of the one signed in for an
 * "own" resource, reads the record (a 404 page for one that does not exist), asks its guards (RecordGuard: reading it,
 * or updating it on the edit form) and tells the panel what the page is of. The bundle's is {@see DefaultCrudPages};
 * decorate it (#[AsDecorator(CrudPages::class)]) to do more when any page of a record opens - remember it among
 * those seen last, log who looked.
 */
interface CrudPages
{
    /**
     * @param string $resource the name of the resource
     * @param string|null $id of the record, as in the address; null on a page of the whole resource
     * @param RecordScreen $screen what the page is: the edit form asks the guards for updating, any other for reading
     *
     * @throws AccessDeniedHttpException the resource is not for the one asking
     * @throws HttpException the record cannot be read: 404 for one that does not exist, 403 for one refused
     */
    public function open(
        Request $request,
        string $resource,
        ?string $id = null,
        RecordScreen $screen = RecordScreen::Details,
    ): CrudContext;
}
