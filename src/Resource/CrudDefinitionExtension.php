<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Changes what a resource shows for the request being answered: a column only somebody with a role sees, the columns
 * of a tenant, an action behind a feature flag, other page sizes for a user. A service, autoconfigured; it gets the
 * resource as declared (and as the extensions before it changed it) and returns it changed with AsCrud::with():
 *
 *     final readonly class MarginColumn implements CrudDefinitionExtension
 *     {
 *         public function __construct(private Actor $actor) {}
 *
 *         public function resource(): string { return 'product'; }
 *
 *         public function extend(AsCrud $crud): AsCrud
 *         {
 *             return $this->actor->isGranted('ROLE_FINANCE')
 *                 ? $crud->with(columns: [...$crud->columns, $crud->column('margin', 'money')])
 *                 : $crud;
 *         }
 *     }
 *
 * Every way to the resource sees what it returns: the list, the details, the forms, the menu, whether the palette
 * offers it. It may also take operations away for the request, $crud->refusing(CrudOperation::Delete) - their buttons
 * go and their pages answer 403 - but never give back what an extension before it refused. What the routes are made
 * of (name, path, scope, own, source, listedIn), the role and the roles stay as declared - the routes are built once
 * for everybody. Asked once a request per resource, by the priority of its tag; it must not depend on
 * CrudRegistry (it is what asks it).
 */
#[AutoconfigureTag(self::TAG)]
interface CrudDefinitionExtension
{
    public const string TAG = 'byfareska_crud.definition_extension';

    /** The resource of an extension of every resource. */
    public const string EVERY = '*';

    /** The name of the resource it changes, as declared; EVERY for all. */
    public function resource(): string;

    /**
     * The resource as it is to be in this request: $crud itself, or $crud->with(...).
     */
    public function extend(AsCrud $crud): AsCrud;
}
