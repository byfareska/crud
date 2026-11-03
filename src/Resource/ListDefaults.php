<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * What a list starts with when its address says nothing: the filters and the order the one looking chose last (kept
 * by a listener of ListStateChanged), a view of their team, "my open orders" for a salesperson. A service,
 * autoconfigured; the first of the resource that says something wins. Its size is PageSizes'.
 */
#[AutoconfigureTag(self::TAG)]
interface ListDefaults
{
    public const string TAG = 'byfareska_crud.list_defaults';

    /** The defaults of every list. */
    public const string EVERY = '*';

    /** The name of the resource, as in #[AsCrud]; EVERY for all. */
    public function resource(): string;

    /**
     * The filters, as in the address of the list (`['status' => 'open', 'owner' => ['comparison' => 'eq', 'value'
     * => '7']]`); [] = none of this one's.
     *
     * @return array<string, mixed>
     */
    public function filters(AsCrud $crud): array;

    /** The order, as in the address ("-createdAt,name"); null = none of this one's. */
    public function sort(AsCrud $crud): ?string;
}
