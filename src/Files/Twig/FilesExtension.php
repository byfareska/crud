<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Twig;

use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileUrls;
use Byfareska\Crud\Files\StoredFile;
use Stringable;
use Twig\Attribute\AsTwigFunction;

/**
 * Files in templates: crud_file_url(reference) - its address (null: none) - and crud_file(reference) - what its
 * library knows of it (StoredFile: its name, its kind, the size of an image).
 */
final readonly class FilesExtension
{
    public function __construct(
        private FileUrls $urls,
        private FileLibraries $libraries,
    ) {
    }

    /**
     * @param string|null $variant a size of the image (byfareska_crud.files.images.variants: "thumbnail",
     *     "preview"...); the image itself without that size
     */
    #[AsTwigFunction('crud_file_url')]
    public function url(string|Stringable|null $reference, ?string $variant = null): ?string
    {
        $reference = $this->reference($reference);

        return $reference instanceof FileReference ? $this->urls->url($reference, $variant) : null;
    }

    #[AsTwigFunction('crud_file')]
    public function file(string|Stringable|null $reference): ?StoredFile
    {
        $reference = $this->reference($reference);

        return $reference instanceof FileReference ? $this->libraries->describe($reference) : null;
    }

    private function reference(string|Stringable|null $reference): ?FileReference
    {
        return $reference instanceof FileReference || $reference === null
            ? $reference
            : FileReference::tryParse((string) $reference);
    }
}
