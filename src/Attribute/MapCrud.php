<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;
use Byfareska\Crud\Page\CrudContext;
use Byfareska\Crud\Page\CrudContextResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Gives a controller of the application the {@see CrudContext} of a resource declared with {@see AsCrud} - for a
 * page of its own next to those of the bundle, e.g. the history of a user:
 *
 *     #[Route('/admin/users/{id}/history', name: 'app_user_history')]
 *     public function history(#[MapCrud('user')] CrudContext $user): Response
 *
 * The page is then like the bundle's: only for somebody the resource (and its panel) is for, a 404 page for a record
 * that does not exist, the record of the one signed in for an "own" resource, the menu of the panel of the resource
 * with the resource lit. Its template may extend @CrudTheme/crud/record.html.twig - see there.
 *
 * A controller that changes the record says what it does, so its guards are asked for that and not only for reading
 * it - a POST that approves an invoice:
 *
 *     #[Route('/admin/invoices/{id}/approve', methods: ['POST'])]
 *     public function approve(#[MapCrud('invoice', update: true, action: 'approve')] CrudContext $invoice): Response
 *
 * (A RunAction does the same without a controller of your own: see RunAction.)
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class MapCrud extends ValueResolver
{
    /**
     * @param string $resource the name of the resource, as in #[AsCrud]
     * @param string|null $id the route parameter that is the id of the record; a route without it is a page of the
     *     whole resource; null = never a record
     * @param bool $update the page changes the record: the resource has to let the one asking update (its roles) and
     *     the guards of Update are asked (RecordGuard), not those of Read
     * @param string|null $action the name of what it does ("approve"): the RecordActionGuards of that action are asked
     *     too, as for an action of the record of that name
     */
    public function __construct(
        public readonly string $resource,
        public readonly ?string $id = 'id',
        public readonly bool $update = false,
        public readonly ?string $action = null,
    ) {
        parent::__construct(CrudContextResolver::class);
    }
}
