<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Testing;

use Byfareska\Crud\Files\Image\ImageResizer;
use Byfareska\Crud\Files\Image\ImageVariant;
use Byfareska\Crud\Files\Image\ImageVariantFailed;
use Byfareska\Crud\Files\Image\VariantFit;
use PHPUnit\Framework\TestCase;

/**
 * What every ImageResizer does, for a resizer of the application to be tested against: a test of it extends this one
 * and gives the resizer. The images it is given are made by GD: without ext-gd the tests are skipped.
 *
 *     final class VipsResizerTest extends ImageResizerContractTestCase
 *     {
 *         protected function resizer(): ImageResizer
 *         {
 *             return new VipsResizer();
 *         }
 *     }
 */
abstract class ImageResizerContractTestCase extends TestCase
{
    abstract protected function resizer(): ImageResizer;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('The images of the tests are made by GD.');
        }
    }

    public function testItReadsJpegAndPngButNoSvg(): void
    {
        $resizer = $this->resizer();

        self::assertTrue($resizer->supports('image/jpeg'));
        self::assertTrue($resizer->supports('image/png'));
        self::assertFalse($resizer->supports('image/svg+xml'));
        self::assertFalse($resizer->supports('application/pdf'));
    }

    public function testInsideTheBox(): void
    {
        $resized = $this->resizer()->resize($this->jpeg(1600, 1200), 'image/jpeg', new ImageVariant('p', 480, 480), 80);

        self::assertSame('image/jpeg', $resized->mimeType);
        self::assertSame([480, 360], [$resized->width, $resized->height]);
        self::assertSame([480, 360], $this->pixels($resized->contents));
    }

    public function testCoveringTheBox(): void
    {
        $variant = new ImageVariant('t', 96, 96, VariantFit::Cover);
        $resized = $this->resizer()->resize($this->jpeg(1600, 1200), 'image/jpeg', $variant, 80);

        self::assertSame([96, 96], $this->pixels($resized->contents));
    }

    public function testNeverBigger(): void
    {
        $resized = $this->resizer()->resize($this->png(200, 100), 'image/png', new ImageVariant('p', 480, 480), 80);

        self::assertSame('image/png', $resized->mimeType);
        self::assertSame([200, 100], $this->pixels($resized->contents));
    }

    public function testWrittenInTheFormatOfTheSize(): void
    {
        $resizer = $this->resizer();
        if (!$resizer->supports('image/webp')) {
            self::markTestSkipped('The resizer writes no WebP here.');
        }

        $resized = $resizer->resize($this->png(800, 600), 'image/png', new ImageVariant('w', 400, format: 'webp'), 80);

        self::assertSame('image/webp', $resized->mimeType);
        self::assertSame([400, 300], $this->pixels($resized->contents));
    }

    public function testWhatIsNoImageFails(): void
    {
        $this->expectException(ImageVariantFailed::class);

        $this->resizer()->resize('no image at all', 'image/jpeg', new ImageVariant('p', 480), 80);
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    protected function jpeg(int $width, int $height): string
    {
        return $this->encoded($width, $height, imagejpeg(...));
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    protected function png(int $width, int $height): string
    {
        return $this->encoded($width, $height, imagepng(...));
    }

    /**
     * @return array{int, int}
     */
    protected function pixels(string $contents): array
    {
        $size = getimagesizefromstring($contents);
        self::assertNotFalse($size, 'What the resizer wrote is no image.');

        return [$size[0], $size[1]];
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param callable(\GdImage): bool $encode
     */
    private function encoded(int $width, int $height, callable $encode): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        ob_start();
        $encode($image);

        return (string) ob_get_clean();
    }
}
