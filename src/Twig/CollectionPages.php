<?php

declare(strict_types=1);

namespace Byfareska\Crud\Twig;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudRegistry;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twig\Attribute\AsTwigFunction;

/**
 * The pages of the whole resource (#[AsCrudPage(collection: true)]) the one looking may open: the buttons next to
 * "new" on its list.
 */
final readonly class CollectionPages
{
    public function __construct(
        private CrudRegistry $registry,
        private CrudAccess $access,
    ) {
    }

    /**
     * @return list<AsCrudPage>
     */
    #[AsTwigFunction('crud_collection_pages')]
    public function of(AsCrud $crud): array
    {
        if ($crud->own) {
            return [];
        }
        $pages = [];
        foreach ($this->registry->pagesOf($crud->name) as $page) {
            if (!$page->collection) {
                continue;
            }
            try {
                $this->access->assertPageGranted($page, $crud);
            } catch (HttpException) {
                continue;
            }
            $pages[] = $page;
        }

        return $pages;
    }
}
