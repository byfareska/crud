<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * Changes what a resource shows for the request being answered: a property only somebody with a role sees, the
 * columns of a tenant, an action behind a feature flag, other page sizes for a user. A service, autoconfigured; it gets
 * the resource as declared (and as the extensions before it changed it) and returns it changed with AsCrud::with():
 *
 *     // #[AsCrud(name: 'product', ..., columns: ['name', 'price' => 'money', 'margin' => 'money'])]
 *     final readonly class MarginForFinance implements CrudDefinitionExtension
 *     {
 *         public function __construct(private Actor $actor) {}
 *
 *         public function resource(): string { return 'product'; }
 *
 *         public function extend(AsCrud $crud): AsCrud
 *         {
 *             return $this->actor->isGranted('ROLE_FINANCE')
 *                 ? $crud
 *                 : $crud->with(hidden: [...$crud->hidden, 'margin']);
 *         }
 *     }
 *
 * A property kept from somebody is hidden (AsCrud::$hidden): then it is no column, no row of the details or the
 * preview, no field of a guessed form (nor prefilled or copied into one), and the list is not searched, ordered nor
 * filtered by it, whatever the source names. Only taking its column away - with(columns: ...) - changes the list
 * alone: the details, the form, the search, the order and the filters still have it. A When of the layout keeps a
 * part off the form and the details only. A form type of the application is its own: a field of it is taken away by
 * a CrudFormExtension.
 *
 * Every way to the resource sees what it returns: the list, the details, the forms, the menu, whether the palette
 * offers it. It may also take operations away for the request, $crud->refusing(CrudOperation::Delete) - their buttons
 * go and their pages answer 403 - but never give back what an extension before it refused. What the routes are made
 * of (name, path, scope, own, source, listedIn), the role and the roles stay as declared - the routes are built once
 * for everybody. Asked once a request per resource, by the priority of its tag; it must not depend on
 * CrudRegistry (it is what asks it).
 */
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
