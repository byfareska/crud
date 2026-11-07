<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Intervention;

use Byfareska\Crud\Files\Image\ImageResizer;
use Byfareska\Crud\Files\Image\ImageVariant;
use Byfareska\Crud\Files\Image\ImageVariantFailed;
use Byfareska\Crud\Files\Image\ResizedImage;
use Byfareska\Crud\Files\Image\VariantFit;
use Intervention\Image\Config;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use InvalidArgumentException;
use Throwable;

/**
 * The sizes of images made by intervention/image (4): with Imagick or GD (byfareska_crud.files.images.resizer: auto,
 * imagick, gd), or a driver of its own (libvips: intervention/image-driver-vips) given as a service.
 *
 * Imagick keeps the colour profile of a photo (ICC: Display P3 of a phone, Adobe RGB), turns CMYK into RGB and
 * reads HEIC; GD drops the profile, so such a photo comes out paler. "auto" takes Imagick when the PHP that resizes
 * has it - decided there, not when the container is built: the console and the web server may differ.
 *
 * Turned the way the camera held it, without the metadata of the image, and an animated GIF as its first frame (a
 * size of every frame would take as long as the frames are many).
 */
final class InterventionImageResizer implements ImageResizer
{
    /** The formats whose encoder has a quality. */
    private const array LOSSY = [Format::JPEG, Format::WEBP, Format::AVIF, Format::HEIC, Format::JP2];

    private ?ImageManager $manager = null;

    /**
     * @param string|DriverInterface $driver "auto" (Imagick when it is there, else GD), "imagick", "gd", or a driver:
     *     made when an image is first resized, so a missing extension fails there, not on every page with an address
     *     of an image
     */
    public function __construct(
        private readonly string|DriverInterface $driver = 'auto',
    ) {
    }

    public function supports(string $mimeType): bool
    {
        $format = Format::tryCreate($mimeType);
        if (!$format instanceof Format) {
            return false;
        }
        try {
            return $this->manager()->driver->supports($format);
        } catch (Throwable) {
            return false;
        }
    }

    public function resize(string $contents, string $mimeType, ImageVariant $variant, int $quality): ResizedImage
    {
        try {
            $format = Format::create($variant->format ?? $mimeType);
            $image = $this->manager()->decodeBinary($contents);
            $image = $variant->fit === VariantFit::Cover && $variant->width !== null && $variant->height !== null
                ? $image->coverDown($variant->width, $variant->height)
                : $image->scaleDown($variant->width, $variant->height);
            $encoded = $image->encodeUsingFormat($format, ...match (true) {
                in_array($format, self::LOSSY, true) => ['quality' => $quality, 'strip' => true],
                $format === Format::PNG => ['strip' => true],
                default => [],
            });

            return new ResizedImage($encoded->toString(), $encoded->mediaType(), $image->width(), $image->height());
        } catch (Throwable $failure) {
            throw new ImageVariantFailed(sprintf(
                'The size "%s" of an image of %s: %s',
                $variant->name,
                $mimeType,
                $failure->getMessage(),
            ), 0, $failure);
        }
    }

    private function manager(): ImageManager
    {
        if (!$this->manager instanceof ImageManager) {
            $config = new Config(autoOrientation: true, decodeAnimation: false, strip: true);
            $driver = $this->driver;
            $this->manager = new ImageManager($driver instanceof DriverInterface ? $driver : match ($driver) {
                'auto' => $this->imagick() ? new ImagickDriver($config) : new GdDriver($config),
                'imagick' => new ImagickDriver($config),
                'gd' => new GdDriver($config),
                default => throw new InvalidArgumentException(sprintf(
                    'The driver of intervention/image is "auto", "imagick", "gd" or a service of a driver, not "%s".',
                    $driver,
                )),
            });
        }

        return $this->manager;
    }

    /** The name of the driver the images are resized with: "imagick", "gd", or that of a driver given. */
    public function driverName(): string
    {
        return strtolower($this->manager()->driver->id());
    }

    private function imagick(): bool
    {
        return extension_loaded('imagick') && class_exists(\Imagick::class);
    }
}
