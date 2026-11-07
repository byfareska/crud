<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Image;

/**
 * How an image is made to fit the box of a size (byfareska_crud.files.images.variants.<name>.fit). Neither makes an
 * image bigger than it is.
 */
enum VariantFit: string
{
    /** Made smaller until it is within the box, its proportions kept: 1600 x 1200 in 480 x 480 is 480 x 360. */
    case Inside = 'inside';

    /** Made smaller until it fills the box, and what goes beyond is cut off: a square thumbnail of a photo. */
    case Cover = 'cover';
}
