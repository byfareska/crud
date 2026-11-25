<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use DateTimeImmutable;

/**
 * A file of a storage and what is known of it: what a library lists, what an upload gives back, what a field shows.
 * A library without a database knows less (no name it was uploaded under: the name of the file instead).
 */
final readonly class StoredFile
{
    /**
     * @param string $name the name it was uploaded under ("Invoice 2026-10.pdf")
     * @param int|null $width of an image, in pixels
     */
    public function __construct(
        public FileReference $reference,
        public string $name,
        public ?string $mimeType = null,
        public ?int $size = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    /** What is known of a file of another site, or of one no library has: its name only. */
    public static function of(FileReference $reference): self
    {
        return new self($reference, $reference->basename());
    }

    /**
     * Whether a browser would run it as a page - HTML, SVG, a script (UploadProfile::ACTIVE), by its MIME type or its
     * extension: opened from where the panel is, it would act as the one looking. The panel shows such an image only
     * as an image (<img>), and never opens such a file from an address of its storage.
     */
    public function isActive(): bool
    {
        return UploadProfile::isActive($this->mimeType, $this->reference->extension());
    }

    public function isImage(): bool
    {
        return $this->mimeType !== null
            ? str_starts_with($this->mimeType, 'image/')
            : in_array($this->reference->extension(), UploadProfile::IMAGE_EXTENSIONS, true);
    }
}
