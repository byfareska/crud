<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudReturn;
use Twig\Attribute\AsTwigFunction;

final readonly class CrudReturnExtension
{
    public function __construct(
        private CrudReturn $return,
    ) {
    }

    /**
     * The address the pages of a record lead back to - see CrudReturn.
     */
    #[AsTwigFunction('crud_return_url')]
    public function url(AsCrud $crud, ?string $id = null): string
    {
        return $this->return->url($crud, $id);
    }
}
