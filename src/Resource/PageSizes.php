<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * How many records a page of a list can have, and which of them it shows at first. The bundle's is
 * {@see DefaultPageSizes}: AsCrud::$perPage and $defaultPerPage, else byfareska_crud.per_page and default_per_page.
 * Alias this interface for sizes that depend on more - the size a user chose last, a device.
 */
interface PageSizes
{
    /**
     * @return non-empty-list<int>
     */
    public function of(AsCrud $crud): array;

    /**
     * The size a list shows at first; one of of().
     */
    public function defaultOf(AsCrud $crud): int;
}
