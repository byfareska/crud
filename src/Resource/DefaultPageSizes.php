<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * How many records a page of a list can have, and which of them it shows at first: those of the resource
 * (AsCrud::$perPage, AsCrud::$defaultPerPage), else byfareska_crud.per_page and byfareska_crud.default_per_page.
 */
final readonly class DefaultPageSizes implements PageSizes
{
    /**
     * @param non-empty-list<int> $sizes
     */
    public function __construct(
        #[Autowire('%byfareska_crud.per_page%')]
        private array $sizes = [15, 30, 50, 100],
        #[Autowire('%byfareska_crud.default_per_page%')]
        private ?int $default = null,
    ) {
    }

    /**
     * @return non-empty-list<int>
     */
    public function of(AsCrud $crud): array
    {
        return $crud->perPage ?? $this->sizes;
    }

    /**
     * The size a list shows at first: the one the resource says, else the one of the configuration - if the resource
     * offers it - else the first it offers.
     */
    public function defaultOf(AsCrud $crud): int
    {
        $sizes = $this->of($crud);
        foreach ([$crud->defaultPerPage, $this->default] as $size) {
            if (in_array($size, $sizes, true)) {
                return $size;
            }
        }

        return $sizes[0];
    }
}
