<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Image;

use Byfareska\Crud\Files\Image\ImageResizer;
use Byfareska\Crud\Files\Image\ImageVariant;
use Byfareska\Crud\Files\Image\ImageVariantFailed;
use Byfareska\Crud\Files\Image\ResizedImage;

/**
 * A resizer without GD: reads the pixels of a PNG or a JPEG and writes what it was asked for - a PNG of its header, or
 * a text naming the format and the pixels.
 */
final class FakeImageResizer implements ImageResizer
{
    /** @var list<string> "<variant> <mime type>" of each size made */
    public array $made = [];

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp'], true);
    }

    public function resize(string $contents, string $mimeType, ImageVariant $variant, int $quality): ResizedImage
    {
        $size = @getimagesizefromstring($contents);
        if ($size === false) {
            throw new ImageVariantFailed('Not an image.');
        }
        [$width, $height] = $variant->sizeOf($size[0], $size[1]);
        $type = $variant->format !== null ? ImageVariant::MIME_TYPES[$variant->format] : $mimeType;
        $this->made[] = $variant->name . ' ' . $type;

        return new ResizedImage(
            $type === 'image/png' ? self::png($width, $height) : sprintf('%s %dx%d', $type, $width, $height),
            $type,
            $width,
            $height,
        );
    }

    /** The header of a PNG of these pixels: enough for getimagesize(). */
    public static function png(int $width, int $height): string
    {
        $header = pack('NN', $width, $height) . "\x08\x06\0\0\0";

        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $header . pack('N', crc32('IHDR' . $header));
    }
}
