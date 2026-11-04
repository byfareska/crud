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
     * @param string|null $variant a smaller kind of the file ("thumbnail"); null = the file itself. The bundle makes
     *     none yet: a variant it does not know is the file itself
     * @return string|null null = it cannot be opened (a storage without addresses)
     */
    public function url(FileReference $reference, ?string $variant = null): ?string;

    /** The file at an address made by url() - of a storage with lasting addresses; null for any other. */
    public function referenceOf(string $url): ?FileReference;
}
