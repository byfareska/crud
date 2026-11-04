<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Files;

/**
 * An item of the gallery of a product: its file, its place, its alternative text.
 */
final class ProductImage
{
    public ?Product $product = null;

    public string $file = '';

    public int $position = 0;

    public ?string $alt = null;
}
