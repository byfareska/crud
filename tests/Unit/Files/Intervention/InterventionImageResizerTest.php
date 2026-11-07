<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Intervention;

use Byfareska\Crud\Files\Image\ImageResizer;
use Byfareska\Crud\Files\Image\ImageVariant;
use Byfareska\Crud\Files\Intervention\InterventionImageResizer;
use Byfareska\Crud\Files\Testing\ImageResizerContractTestCase;
use Byfareska\Crud\Files\Image\ImageVariantFailed;

/**
 * The sizes of images made by intervention/image with GD.
 */
final class InterventionImageResizerTest extends ImageResizerContractTestCase
{
    protected function resizer(): ImageResizer
    {
        return new InterventionImageResizer('gd');
    }

    public function testTurnedTheWayTheCameraHeldItWithoutTheMetadata(): void
    {
        $jpeg = $this->jpeg(800, 600);
        // EXIF as a camera writes it (APP1 right after the start of the JPEG): orientation 6, turned 90 degrees.
        $tiff = 'II*' . "\0" . pack('V', 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, 6, 0) . pack('V', 0);
        $exif = "Exif\0\0" . $tiff;
        $held = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);

        $resized = $this->resizer()->resize($held, 'image/jpeg', new ImageVariant('p', 400, 400), 80);

        self::assertSame([300, 400], $this->pixels($resized->contents));
        self::assertStringNotContainsString("Exif\0\0", $resized->contents);
    }

    public function testAnImageNoBrowserShowsIsMadeAJpeg(): void
    {
        $bmp = imagecreatetruecolor(800, 600);
        self::assertNotFalse($bmp);
        ob_start();
        imagebmp($bmp);
        $contents = (string) ob_get_clean();

        $resized = $this->resizer()->resize($contents, 'image/bmp', new ImageVariant('p', 400)->of('bmp'), 80);

        self::assertSame('image/jpeg', $resized->mimeType);
        self::assertSame([400, 300], $this->pixels($resized->contents));
    }

    public function testAnUnknownDriverFailsWhenAnImageIsResized(): void
    {
        $resizer = new InterventionImageResizer('vips');

        // Not when it is made: a page with addresses of images does not need the driver.
        self::assertFalse($resizer->supports('image/jpeg'));
        $this->expectException(ImageVariantFailed::class);
        $resizer->resize($this->jpeg(10, 10), 'image/jpeg', new ImageVariant('p', 5), 80);
    }
}
