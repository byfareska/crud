<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Image;

use Byfareska\Crud\Files\Image\ImageVariant;
use Byfareska\Crud\Files\Image\VariantFit;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A size of images: the box an image is made to fit, never bigger than the image.
 */
final class ImageVariantTest extends TestCase
{
    /** @return iterable<string, array{ImageVariant, int, int, array{int, int}}> */
    public static function sizes(): iterable
    {
        yield 'inside a square' => [new ImageVariant('p', 480, 480), 1600, 1200, [480, 360]];
        yield 'inside, upright' => [new ImageVariant('p', 480, 480), 1200, 1600, [360, 480]];
        yield 'only a width' => [new ImageVariant('w', 800), 1600, 1200, [800, 600]];
        yield 'only a height' => [new ImageVariant('h', height: 300), 1600, 1200, [400, 300]];
        yield 'inside, never bigger' => [new ImageVariant('p', 480, 480), 200, 100, [200, 100]];
        yield 'cover a square' => [new ImageVariant('t', 96, 96, VariantFit::Cover), 1600, 1200, [96, 96]];
        yield 'cover a wide box' => [new ImageVariant('b', 300, 100, VariantFit::Cover), 1200, 1600, [300, 100]];
        // Smaller than the box: only cut to its shape, as intervention/image's coverDown() does.
        yield 'cover, never bigger' => [new ImageVariant('t', 96, 96, VariantFit::Cover), 50, 30, [30, 30]];
    }

    /**
     * @param array{int, int} $expected
     */
    #[DataProvider('sizes')]
    public function testHowBigAnImageIsMade(ImageVariant $variant, int $width, int $height, array $expected): void
    {
        self::assertSame($expected, $variant->sizeOf($width, $height));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function mistakes(): iterable
    {
        yield 'no box' => [[]];
        yield 'a cover without a height' => [['width' => 100, 'fit' => 'cover']];
        yield 'no format of the list' => [['width' => 100, 'format' => 'tiff']];
        yield 'a quality too high' => [['width' => 100, 'quality' => 101]];
        yield 'no pixels' => [['width' => 0]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('mistakes')]
    public function testAMistakeOfTheConfiguration(array $config): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore argument.type (what a mistaken configuration gives) */
        ImageVariant::fromConfig('large', $config);
    }

    public function testTheNameIsADirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ImageVariant('../large', 100);
    }

    public function testTheBundlesOwnSizes(): void
    {
        $thumbnail = ImageVariant::fromConfig(ImageVariant::THUMBNAIL, ImageVariant::DEFAULTS[ImageVariant::THUMBNAIL]);
        $preview = ImageVariant::fromConfig(ImageVariant::PREVIEW, ImageVariant::DEFAULTS[ImageVariant::PREVIEW]);

        self::assertSame([96, 96], $thumbnail->sizeOf(4000, 3000));
        self::assertSame([480, 360], $preview->sizeOf(4000, 3000));
    }
}
