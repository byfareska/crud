<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Image;

/**
 * A size of an image as an ImageResizer made it.
 */
final readonly class ResizedImage
{
    public function __construct(
        public string $contents,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {
    }
}
