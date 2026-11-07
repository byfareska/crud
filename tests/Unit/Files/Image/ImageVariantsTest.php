<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Image;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\Image\ImageVariantFailed;
use Byfareska\Crud\Files\Image\VariantStrategy;
use PHPUnit\Framework\TestCase;

/**
 * The sizes of an image are files of its storage, under a directory named after the size and the path of the image.
 */
final class ImageVariantsTest extends TestCase
{
    private ImagesKit $kit;

    protected function setUp(): void
    {
        $this->kit = new ImagesKit();
    }

    public function testWhereASizeIsKept(): void
    {
        $variants = $this->kit->variants;
        $image = FileReference::parse('public://ab/cd/x7k2.png');

        $of = static fn (string $name): string => (string) $variants->referenceOf($image, $name);

        self::assertSame('public://_variants/thumbnail/ab/cd/x7k2.png', $of('thumbnail'));
        // Written as WebP: its own extension after the image's.
        self::assertSame('public://_variants/large/ab/cd/x7k2.png.webp', $of('large'));
        self::assertSame(
            'public://_variants/large/ab/cd/x7k2.webp',
            (string) $variants->referenceOf(FileReference::parse('public://ab/cd/x7k2.webp'), 'large'),
        );
    }

    public function testTheImageOfASize(): void
    {
        $variants = $this->kit->variants;
        $paths = ['public://ab/cd/x7k2.png', 'public://ab/cd/x7k2.webp', 'public://x.jpg', 'public://x.heic'];
        foreach ([...$paths, 'public://x.TIF'] as $path) {
            $image = FileReference::parse($path);
            foreach (['thumbnail', 'preview', 'large'] as $name) {
                $parsed = $variants->parse($variants->referenceOf($image, $name));
                self::assertNotNull($parsed);
                self::assertSame([$path, $name], [(string) $parsed[0], $parsed[1]]);
            }
        }
        self::assertNull($variants->parse(FileReference::parse('public://ab/cd/x7k2.png')));
        $image = FileReference::parse('public://ab/x.png');
        self::assertSame($image, $variants->originalOf($image), 'No size: itself.');
    }

    public function testASizeOfAnImageNoBrowserShowsIsAJpeg(): void
    {
        $variants = $this->kit->variants;
        $image = FileReference::parse('public://ab/x.heic');

        $of = static fn (string $name): string => (string) $variants->referenceOf($image, $name);

        self::assertSame('public://_variants/thumbnail/ab/x.heic.jpg', $of('thumbnail'));
        self::assertSame('public://_variants/large/ab/x.heic.webp', $of('large'));
        self::assertSame('jpg', $variants->get('thumbnail')->of('heic')->format);
        self::assertNull($variants->get('thumbnail')->of('png')->format);
    }

    public function testASizeNoLongerConfigured(): void
    {
        $variants = $this->kit->variants;

        $parsed = $variants->parse(FileReference::parse('public://_variants/old/ab/x.png.avif'));
        self::assertNotNull($parsed);
        self::assertSame(['public://ab/x.png', 'old'], [(string) $parsed[0], $parsed[1]]);
        self::assertSame(
            'public://ab/x.png',
            (string) $variants->originalOf(FileReference::parse('public://_variants/old/ab/x.png')),
        );
    }

    public function testWhatHasSizes(): void
    {
        $variants = $this->kit->variants;

        self::assertTrue($variants->applies(FileReference::parse('public://a.png'), 'thumbnail'));
        self::assertTrue($variants->applies(FileReference::parse('lazy://a.jpg'), 'thumbnail'));
        self::assertFalse($variants->applies(FileReference::parse('public://a.png'), 'huge'), 'No such size.');
        self::assertFalse($variants->applies(FileReference::parse('public://a.svg'), 'thumbnail'), 'Any size.');
        self::assertFalse($variants->applies(FileReference::parse('public://a.gif'), 'thumbnail'), 'Not read.');
        self::assertFalse($variants->applies(FileReference::parse('public://a.pdf'), 'thumbnail'));
        self::assertFalse($variants->applies(FileReference::parse('plain://a.png'), 'thumbnail'), 'variants: none');
        self::assertFalse($variants->applies(FileReference::parse('https://example.com/a.png'), 'thumbnail'));
        self::assertFalse($variants->applies(FileReference::parse('public://_variants/preview/a.png'), 'thumbnail'));
        self::assertSame(VariantStrategy::Lazy, $variants->strategy('lazy'));
        self::assertSame(VariantStrategy::None, $variants->strategy('nowhere'));
    }

    public function testASizeIsMadeOnce(): void
    {
        $image = $this->kit->image('public://ab/a.png');
        $variants = $this->kit->variants;

        $thumbnail = $variants->make($image, 'thumbnail');
        $variants->make($image, 'thumbnail');

        self::assertTrue($this->kit->has($thumbnail));
        self::assertTrue($variants->exists($image, 'thumbnail'));
        self::assertSame(['thumbnail image/png'], $this->kit->resizer->made);
        $size = getimagesizefromstring($this->kit->filesystem->read($thumbnail->path));
        self::assertNotFalse($size);
        self::assertSame([96, 96], [$size[0], $size[1]]);

        $variants->make($image, 'thumbnail', overwrite: true);
        self::assertCount(2, $this->kit->resizer->made);
    }

    public function testEverySizeOfAnImage(): void
    {
        $image = $this->kit->image('public://ab/a.png');

        self::assertSame([], $this->kit->variants->makeAll($image));

        self::assertSame(['thumbnail image/png', 'preview image/png', 'large image/webp'], $this->kit->resizer->made);
        self::assertTrue($this->kit->has(FileReference::parse('public://_variants/large/ab/a.png.webp')));
    }

    public function testAnImageOfTooManyPixelsHasNoSizes(): void
    {
        $kit = new ImagesKit(maxPixels: 1000);
        $image = $kit->image('public://ab/a.png', 100, 100);

        self::assertSame(['thumbnail', 'preview', 'large'], $kit->variants->makeAll($image));
        self::assertSame([], $kit->resizer->made);
        self::assertStringContainsString('more than 1000', $kit->logger->messages[0]);

        $this->expectException(ImageVariantFailed::class);
        $kit->variants->make($image, 'thumbnail');
    }

    public function testAFileThatIsNoImage(): void
    {
        $this->kit->filesystem->write('ab/broken.png', 'not an image');

        $this->expectException(ImageVariantFailed::class);
        $this->kit->variants->make(FileReference::parse('public://ab/broken.png'), 'thumbnail');
    }

    public function testTheSizesGoWithTheImage(): void
    {
        $image = $this->kit->image('public://ab/a.png');
        $this->kit->variants->makeAll($image);

        $this->kit->variants->remove($image);

        self::assertSame([], $this->kit->filesystem->listContents('_variants', true)
            ->filter(static fn ($item): bool => $item->isFile())
            ->toArray());
        self::assertTrue($this->kit->has($image), 'Only its sizes.');
    }
}
