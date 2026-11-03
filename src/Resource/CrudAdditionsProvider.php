<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrudDetailsSection;
use Byfareska\Crud\Attribute\AsCrudFormSection;
use Byfareska\Crud\Attribute\AsCrudPage;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Pages and sections declared by code rather than by #[AsCrudPage], #[AsCrudFormSection] and #[AsCrudDetailsSection]
 * on a class: those of a module switched on in the configuration, one for each resource a list names - "ERP
 * integration" under the details of every resource of a YAML file. A service, autoconfigured; what it yields is next
 * to what the attributes declare.
 *
 *     final readonly class ErpSections implements CrudAdditionsProvider
 *     {
 *         public function __construct(#[Autowire(param: 'app.erp.resources')] private array $resources) {}
 *
 *         public function detailsSections(): iterable
 *         {
 *             foreach ($this->resources as $resource) {
 *                 yield new AsCrudDetailsSection($resource, 'erp', 'App:ErpStatus');
 *             }
 *         }
 *
 *         public function pages(): iterable { return []; }
 *         public function formSections(): iterable { return []; }
 *     }
 *
 * Its pages have routes, which are kept in the cache: as for a CrudResourceProvider, what it yields is the same in
 * every request.
 */
#[AutoconfigureTag(self::TAG)]
interface CrudAdditionsProvider
{
    public const string TAG = 'byfareska_crud.additions_provider';

    /** @return iterable<AsCrudPage> */
    public function pages(): iterable;

    /** @return iterable<AsCrudFormSection> */
    public function formSections(): iterable;

    /** @return iterable<AsCrudDetailsSection> */
    public function detailsSections(): iterable;
}
