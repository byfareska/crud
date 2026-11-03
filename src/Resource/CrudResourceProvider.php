<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Resources declared by code rather than by #[AsCrud] on a class: made from the configuration, one per entity of a
 * kind, those of a module that is switched on. A service, autoconfigured; its resources are next to those of the
 * attributes, with names of their own.
 *
 *     final readonly class ReportResources implements CrudResourceProvider
 *     {
 *         public function __construct(#[Autowire(param: 'app.reports')] private array $reports) {}
 *
 *         public function resources(): iterable
 *         {
 *             foreach ($this->reports as $name => $report) {
 *                 yield new AsCrud(name: "report_$name", path: "/reports/$name", icon: 'graph-up', ...);
 *             }
 *         }
 *     }
 *
 * The routes are made of them, and the routes are kept in the cache: what it yields is the same in every request -
 * it does not depend on who is looking, nor on the request (that is a {@see CrudDefinitionExtension}). A change of
 * the file of the class rebuilds the routes in debug; one of the configuration or the data it reads needs
 * cache:clear.
 */
#[AutoconfigureTag(self::TAG)]
interface CrudResourceProvider
{
    public const string TAG = 'byfareska_crud.resource_provider';

    /**
     * @return iterable<AsCrud>
     */
    public function resources(): iterable;
}
