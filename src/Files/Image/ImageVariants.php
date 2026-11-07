<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Image;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileStorageFailed;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\StoredFile;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

/**
 * The sizes of images (byfareska_crud.files.images): where each is kept, making them, deleting them with the image.
 *
 * A size of an image is a file of the same storage, under a directory of its own named after the size and the path of
 * the image: "public://ab/cd/x7k2.jpg" is "public://_variants/thumbnail/ab/cd/x7k2.jpg" - and
 * "_variants/large/ab/cd/x7k2.jpg.webp" for a size written as WebP. The path says which image it is a size of, so the
 * address of a size in rich text is read back as the image (originalOf()). A size is made again (generate
 * --overwrite) after its box is changed in the configuration: the address stays the same.
 *
 * SVG and the images the resizer cannot read have no sizes: each size of them is the image itself.
 */
final readonly class ImageVariants
{
    /** @var array<string, ImageVariant> */
    private array $variants;

    /**
     * @param iterable<ImageVariant> $variants
     * @param string $directory the directory of a storage the sizes are under
     * @param int $maxPixels an image with more (width x height) gets no sizes: decoding it would take too much memory
     * @param int $quality of a lossy format, for a size that does not say
     */
    public function __construct(
        private FileStorages $storages,
        private ImageResizer $resizer,
        iterable $variants,
        private string $directory = '_variants',
        private int $maxPixels = 40_000_000,
        private int $quality = 82,
        private ?LoggerInterface $logger = null,
    ) {
        $all = [];
        foreach ($variants as $variant) {
            $all[$variant->name] = $variant;
        }
        $this->variants = $all;
        if (preg_match('/^[a-z0-9_.-]+$/i', $directory) !== 1 || trim($directory, '.') === '') {
            throw new InvalidArgumentException(sprintf(
                'The directory of the sizes of images is one name of letters, digits, ".", "_" and "-": "%s".',
                $directory,
            ));
        }
    }

    /**
     * @return array<string, ImageVariant> by name
     */
    public function all(): array
    {
        return $this->variants;
    }

    public function has(string $name): bool
    {
        return isset($this->variants[$name]);
    }

    /**
     * @throws InvalidArgumentException no such size
     */
    public function get(string $name): ImageVariant
    {
        return $this->variants[$name] ?? throw new InvalidArgumentException(sprintf(
            'There is no size of images "%s" (byfareska_crud.files.images.variants has %s).',
            $name,
            implode(', ', array_keys($this->variants)) ?: 'none',
        ));
    }

    /** The directory of a storage the sizes are under. */
    public function directory(): string
    {
        return $this->directory;
    }

    /** When the images of a storage get their sizes; None for a storage there is not. */
    public function strategy(string $storage): VariantStrategy
    {
        return $this->storages->has($storage) ? $this->storages->settings($storage)->variants : VariantStrategy::None;
    }

    /**
     * Whether a file has this size: a size of the configuration, of an image the resizer reads (by its extension), in
     * a storage that makes sizes - not a file of another site, nor a size itself.
     */
    public function applies(FileReference $reference, string $name): bool
    {
        return isset($this->variants[$name])
            && !$reference->isExternal()
            && $this->strategy($reference->storage) !== VariantStrategy::None
            && !$this->isVariant($reference)
            && $this->readable($reference);
    }

    /** The file of a size of an image, made or not. */
    public function referenceOf(FileReference $image, string $name): FileReference
    {
        $variant = $this->get($name);
        $extension = $image->extension();
        $own = $variant->extensionOf($extension);

        return new FileReference(
            $image->storage,
            $this->directory . '/' . $name . '/' . $image->path . ($own !== $extension ? '.' . $own : ''),
        );
    }

    /** Whether a file is under the directory of the sizes. */
    public function isVariant(FileReference $reference): bool
    {
        return !$reference->isExternal() && str_starts_with($reference->path, $this->directory . '/');
    }

    /**
     * The image a file of a size is of, and the name of the size; null for a file that is no size.
     *
     * @return array{FileReference, string}|null
     */
    public function parse(FileReference $reference): ?array
    {
        if (!$this->isVariant($reference)) {
            return null;
        }
        $rest = substr($reference->path, strlen($this->directory) + 1);
        $slash = strpos($rest, '/');
        if ($slash === false || $slash === 0) {
            return null;
        }
        $name = substr($rest, 0, $slash);
        $path = substr($rest, $slash + 1);
        $variant = $this->variants[$name] ?? null;
        if ($variant !== null) {
            // An extension of the size after the image's ("x.png.webp", "x.heic.jpg"): what the size makes of it.
            $last = pathinfo($path, PATHINFO_EXTENSION);
            $stripped = $last === '' ? $path : substr($path, 0, -strlen($last) - 1);
            $inner = strtolower(pathinfo($stripped, PATHINFO_EXTENSION));
            if ($inner !== '' && $inner !== strtolower($last) && $variant->extensionOf($inner) === $last) {
                $path = $stripped;
            }
        } elseif (preg_match('/\.[^.\/]+\.[^.\/]+$/', $path) === 1) {
            // A size no longer configured: an image has one extension, so a second one is the format of the size.
            $path = (string) preg_replace('/\.[^.\/]+$/', '', $path);
        }
        $image = FileReference::tryParse($reference->storage . '://' . $path);

        return $image instanceof FileReference ? [$image, $name] : null;
    }

    /** The image a file of a size is of; the file itself when it is no size. */
    public function originalOf(FileReference $reference): FileReference
    {
        return $this->parse($reference)[0] ?? $reference;
    }

    /** Whether the file of a size of an image is there. */
    public function exists(FileReference $image, string $name): bool
    {
        return $this->storages->storage($image->storage)->exists($this->referenceOf($image, $name)->path);
    }

    /**
     * Makes a size of an image, unless it is there already.
     *
     * @param bool $overwrite make it again when it is there (after its box was changed)
     * @return FileReference the file of the size
     *
     * @throws ImageVariantFailed the image has no such size, cannot be read, has too many pixels
     * @throws FileStorageFailed the storage could not read or write it
     */
    public function make(FileReference $image, string $name, bool $overwrite = false): FileReference
    {
        $variant = $this->get($name);
        if (!$this->applies($image, $name)) {
            throw new ImageVariantFailed(sprintf('"%s" has no size "%s".', $image, $name));
        }
        $target = $this->referenceOf($image, $name);
        $storage = $this->storages->storage($image->storage);
        if (!$overwrite && $storage->exists($target->path)) {
            return $target;
        }

        $stream = $storage->readStream($image->path);
        try {
            $contents = stream_get_contents($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if ($contents === false || $contents === '') {
            throw new ImageVariantFailed(sprintf('"%s" cannot be read.', $image));
        }
        $info = @getimagesizefromstring($contents);
        if ($info !== false && $info[0] * $info[1] > $this->maxPixels) {
            throw new ImageVariantFailed(sprintf(
                '"%s" has %d x %d pixels, more than %d (byfareska_crud.files.images.max_pixels).',
                $image,
                $info[0],
                $info[1],
                $this->maxPixels,
            ));
        }
        $mimeType = $info !== false && $info['mime'] !== '' ? $info['mime'] : $this->mimeTypeOf($image->extension());
        if ($mimeType === null || !$this->resizer->supports($mimeType)) {
            throw new ImageVariantFailed(sprintf('"%s" is no image the resizer reads.', $image));
        }

        $resized = $this->resizer->resize(
            $contents,
            $mimeType,
            $variant->of($image->extension()),
            $variant->quality ?? $this->quality,
        );
        $out = fopen('php://temp', 'w+b');
        if ($out === false) {
            throw new FileStorageFailed('No temporary stream for a size of an image.');
        }
        try {
            fwrite($out, $resized->contents);
            rewind($out);
            $storage->write($target->path, $out, $resized->mimeType);
        } finally {
            fclose($out);
        }

        return $target;
    }

    /**
     * Makes every size of an image that is not there yet; one that fails is logged, and the image is shown in its
     * place.
     *
     * @return list<string> the names of the sizes that could not be made
     */
    public function makeAll(FileReference $image, bool $overwrite = false): array
    {
        $failed = [];
        foreach (array_keys($this->variants) as $name) {
            if (!$this->applies($image, $name)) {
                continue;
            }
            try {
                $this->make($image, $name, $overwrite);
            } catch (ImageVariantFailed | FileStorageFailed $failure) {
                $failed[] = $name;
                $this->failed($image, $name, $failure);
            }
        }

        return $failed;
    }

    /** Deletes every size of an image there is, of the sizes configured. */
    public function remove(FileReference $image): void
    {
        if ($image->isExternal() || $this->isVariant($image) || !$this->storages->has($image->storage)) {
            return;
        }
        $storage = $this->storages->storage($image->storage);
        foreach (array_keys($this->variants) as $name) {
            $storage->delete($this->referenceOf($image, $name)->path);
        }
    }

    /**
     * How big a size of an image is, by the pixels the library knows of it; nulls when it knows none.
     *
     * @return array{int|null, int|null}
     */
    public function dimensionsOf(StoredFile $image, string $name): array
    {
        if ($image->width === null || $image->height === null) {
            return [null, null];
        }

        return $this->get($name)->sizeOf($image->width, $image->height);
    }

    /** What the file of a size is, by its extension. */
    public function mimeTypeOf(string $extension): ?string
    {
        $extension = strtolower($extension);
        if (isset(ImageVariant::MIME_TYPES[$extension])) {
            return ImageVariant::MIME_TYPES[$extension];
        }
        if ($extension === '' || !class_exists(MimeTypes::class)) {
            return null;
        }

        return MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? null;
    }

    public function failed(FileReference $image, string $name, Throwable $failure): void
    {
        $this->logger?->warning('The size "{variant}" of "{image}" could not be made: {message}', [
            'variant' => $name,
            'image' => (string) $image,
            'message' => $failure->getMessage(),
            'exception' => $failure,
        ]);
    }

    /** An image of a kind the resizer reads, by its extension: no SVG (drawn at any size already). */
    private function readable(FileReference $reference): bool
    {
        $mimeType = $this->mimeTypeOf($reference->extension());

        return $mimeType !== null
            && str_starts_with($mimeType, 'image/')
            && $mimeType !== 'image/svg+xml'
            && $this->resizer->supports($mimeType);
    }
}
