<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Image;

use Byfareska\Crud\Event\FileUploaded;

/**
 * Makes the sizes of an uploaded image in a storage that makes them on upload (VariantStrategy::Eager). One that
 * cannot be made is logged, and the upload stays: the image is shown in its place.
 */
final readonly class ImageVariantsListener
{
    public function __construct(
        private ImageVariants $variants,
    ) {
    }

    public function uploaded(FileUploaded $event): void
    {
        $reference = $event->file->reference;
        if ($this->variants->strategy($reference->storage) === VariantStrategy::Eager) {
            $this->variants->makeAll($reference);
        }
    }
}
