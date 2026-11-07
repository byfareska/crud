<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Image;

use RuntimeException;

/**
 * A size of an image could not be made: the image cannot be read, has too many pixels, or its format cannot be
 * written. The image itself is shown in its place.
 */
final class ImageVariantFailed extends RuntimeException
{
}
