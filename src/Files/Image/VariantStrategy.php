<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Image;

/**
 * When a storage gets the sizes of its images (byfareska_crud.files.storages.<name>.variants).
 */
enum VariantStrategy: string
{
    /**
     * Every size is made when an image is uploaded, and its address points at the file of the size. Works with any
     * storage and any web server; images uploaded before need byfareska:crud:files:variants:generate.
     */
    case Eager = 'eager';

    /**
     * A size is made when it is first asked for, through an address of the panel (or the signed download): nothing is
     * made that nobody looks at, but every image of a public storage is opened through PHP - and only by those the
     * panel is for.
     */
    case Lazy = 'lazy';

    /** No sizes: every size of an image is the image itself. */
    case None = 'none';
}
