<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * The address a file is opened at in the browser, and back. The bundle's is {@see StorageFileUrls}, by the "url" of
 * each storage; a storage may name a service of its own ("urls": a CDN with its own signatures, an API of images).
 */
interface FileUrls
{
    /**
     * @param string|null $variant a size of an image (byfareska_crud.files.images.variants: "thumbnail",
     *     "preview"...); null = the file itself. A size the file has not (no images, not an image, SVG) is the file
     *     itself
     * @return string|null null = it cannot be opened (a storage without addresses)
     */
    public function url(FileReference $reference, ?string $variant = null): ?string;

    /**
     * The file at an address made by url() - of a storage with lasting addresses; null for any other. The address of
     * a size of an image is the image (the files a rich text uses are the images, whatever size it shows).
     */
    public function referenceOf(string $url): ?FileReference;
}
