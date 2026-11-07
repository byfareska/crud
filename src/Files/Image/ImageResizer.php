<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Image;

/**
 * What makes the sizes of images (byfareska_crud.files.images.resizer): the bundle's is the one of intervention/image
 * ({@see \Byfareska\Crud\Files\Intervention\InterventionImageResizer}, with GD or Imagick); another - libvips, an API
 * of images - implements this interface, tested by {@see \Byfareska\Crud\Files\Testing\ImageResizerContractTestCase}.
 *
 * A size is turned the way the camera held it (EXIF), and keeps none of the metadata of the image (no place it was
 * taken at).
 */
interface ImageResizer
{
    /** Whether it reads images of this MIME type, and writes them so when a size names no format. */
    public function supports(string $mimeType): bool;

    /**
     * @param string $contents the bytes of the image
     * @param string $mimeType what the image is, read from its bytes
     *
     * @throws ImageVariantFailed it cannot be read or written so
     */
    public function resize(string $contents, string $mimeType, ImageVariant $variant, int $quality): ResizedImage;
}
