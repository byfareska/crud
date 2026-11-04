<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * An object of the application that is a file in a field: an item of a gallery (ProductImage, with its position and
 * "alt"). The lists and details show it as the file - a thumbnail in place of its id.
 */
interface ProvidesFile
{
    public function fileItem(): FileItem;
}
