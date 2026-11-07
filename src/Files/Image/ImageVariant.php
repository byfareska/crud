<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Image;

use InvalidArgumentException;

/**
 * A size of images (byfareska_crud.files.images.variants.<name>): the box an image is made to fit, and what it is
 * written as. The bundle's own are "thumbnail" (the lists, the field of files) and "preview" (the details, the window
 * of the library, a gallery); an application names more, and may change those two.
 */
final readonly class ImageVariant
{
    /** The thumbnails of the lists and of the field of files. */
    public const string THUMBNAIL = 'thumbnail';

    /** The images of the details, the window of the library and a gallery. */
    public const string PREVIEW = 'preview';

    /** The sizes of the bundle, as an application that names none of them gets them. */
    public const array DEFAULTS = [
        self::THUMBNAIL => ['width' => 96, 'height' => 96, 'fit' => 'cover'],
        self::PREVIEW => ['width' => 480, 'height' => 480, 'fit' => 'inside'],
    ];

    /** What the name of a size looks like: it is a directory of the storage. */
    public const string NAME = '/^[a-z][a-z0-9_-]*$/';

    /** The formats a size may be written as, by their extension. */
    public const array FORMATS = ['jpg', 'png', 'gif', 'webp', 'avif'];

    /** The extensions of the formats a browser shows: an image of another format has its sizes in JPEG. */
    public const array SHOWN = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    /** The MIME type of each of FORMATS. */
    public const array MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    /**
     * @param int|null $width the most pixels across; null = any (only the height counts)
     * @param int|null $height the most pixels down; null = any
     * @param string|null $format the extension it is written as (one of FORMATS); null = the format of the image -
     *     JPEG for one no browser shows (HEIC, TIFF, BMP)
     * @param int|null $quality of a lossy format, 1-100; null = that of byfareska_crud.files.images.quality
     * @param string|null $label what it is called where it is chosen (a field of rich text): a translation key or a
     *     text; null = the translation of "file.size.<name>", else the name
     */
    public function __construct(
        public string $name,
        public ?int $width = null,
        public ?int $height = null,
        public VariantFit $fit = VariantFit::Inside,
        public ?string $format = null,
        public ?int $quality = null,
        public ?string $label = null,
    ) {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The name of a size of images is lower-case letters, digits, "_" and "-", starting with a letter: '
                . '"%s".',
                $name,
            ));
        }
        if ($width === null && $height === null) {
            throw new InvalidArgumentException(sprintf('The size "%s" says neither a width nor a height.', $name));
        }
        if ($width !== null && $width < 1 || $height !== null && $height < 1) {
            throw new InvalidArgumentException(sprintf('The width and the height of "%s" are 1 or more.', $name));
        }
        if ($fit === VariantFit::Cover && ($width === null || $height === null)) {
            throw new InvalidArgumentException(sprintf(
                'The size "%s" covers a box: it needs a width and a height.',
                $name,
            ));
        }
        if ($format !== null && !in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException(sprintf(
                'The size "%s" is written as "%s": a format is one of %s.',
                $name,
                $format,
                implode(', ', self::FORMATS),
            ));
        }
        if ($quality !== null && ($quality < 1 || $quality > 100)) {
            throw new InvalidArgumentException(sprintf('The quality of "%s" is 1 to 100.', $name));
        }
    }

    /**
     * @param array{
     *     width?: int|null,
     *     height?: int|null,
     *     fit?: string,
     *     format?: string|null,
     *     quality?: int|null,
     *     label?: string|null,
     * } $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        return new self(
            $name,
            $config['width'] ?? null,
            $config['height'] ?? null,
            VariantFit::from($config['fit'] ?? VariantFit::Inside->value),
            $config['format'] ?? null,
            $config['quality'] ?? null,
            $config['label'] ?? null,
        );
    }

    /**
     * How big an image of these pixels is made: never bigger than it is.
     *
     * @return array{int, int}
     */
    public function sizeOf(int $width, int $height): array
    {
        if ($width < 1 || $height < 1) {
            return [max(0, $width), max(0, $height)];
        }
        if ($this->fit === VariantFit::Cover && $this->width !== null && $this->height !== null) {
            // The biggest part of the image in the shape of the box, made smaller to the box when it is bigger.
            $ratio = $this->width / $this->height;
            [$cropWidth, $cropHeight] = $width / $height > $ratio
                ? [max(1, (int) round($height * $ratio)), $height]
                : [$width, max(1, (int) round($width / $ratio))];

            return $cropWidth > $this->width ? [$this->width, $this->height] : [$cropWidth, $cropHeight];
        }
        $scale = min(
            1.0,
            $this->width !== null ? $this->width / $width : 1.0,
            $this->height !== null ? $this->height / $height : 1.0,
        );

        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
    }

    /** The extension of the file of this size of an image with that extension. */
    public function extensionOf(string $extension): string
    {
        return $this->format ?? (in_array(strtolower($extension), self::SHOWN, true) ? $extension : 'jpg');
    }

    /** This size of an image with that extension: written as JPEG when the image is of a format no browser shows. */
    public function of(string $extension): self
    {
        $own = $this->extensionOf($extension);

        return $this->format !== null || $own === $extension
            ? $this
            : new self($this->name, $this->width, $this->height, $this->fit, $own, $this->quality, $this->label);
    }
}
