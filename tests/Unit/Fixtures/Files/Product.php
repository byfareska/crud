<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Files;

use Byfareska\Crud\Attribute\CrudFile;

/**
 * A product with a gallery of its own items (ProductImage), added and removed through its adder and remover as
 * Doctrine's collections are.
 */
final class Product
{
    /** @var list<ProductImage> */
    #[CrudFile(multiple: true, accept: ['images'], meta: ['alt'], class: ProductImage::class)]
    private array $images = [];

    /**
     * @return list<ProductImage>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): void
    {
        $image->product = $this;
        $this->images[] = $image;
    }

    public function removeImage(ProductImage $image): void
    {
        $this->images = array_values(array_filter(
            $this->images,
            static fn (ProductImage $kept): bool => $kept !== $image,
        ));
    }
}
