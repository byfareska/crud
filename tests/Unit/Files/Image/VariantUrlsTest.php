<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Image;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StorageFileUrls;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * The address of a size of an image: its file, the panel's address that makes it, or its signed download - and back.
 */
final class VariantUrlsTest extends TestCase
{
    private ImagesKit $kit;

    protected function setUp(): void
    {
        $this->kit = new ImagesKit();
    }

    public function testASizeMadeOnUploadIsItsFile(): void
    {
        $image = FileReference::parse('public://ab/cd/a.png');

        self::assertSame('/uploads/ab/cd/a.png', $this->kit->urls->url($image));
        self::assertSame('/uploads/_variants/thumbnail/ab/cd/a.png', $this->kit->urls->url($image, 'thumbnail'));
        self::assertSame('/uploads/_variants/large/ab/cd/a.png.webp', $this->kit->urls->url($image, 'large'));
    }

    public function testASizeMadeWhenAskedForIsAnAddressOfThePanel(): void
    {
        $url = (string) $this->kit->urls->url(FileReference::parse('lazy://ab/a.png'), 'thumbnail');

        self::assertStringStartsWith('http://panel.example.com/admin/_files/variant?', $url);
        self::assertTrue($this->kit->signer->check($url));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('lazy://ab/a.png', $query['ref']);
        self::assertSame('thumbnail', $query['variant']);
        self::assertArrayNotHasKey('_expiration', $query, 'The same address every time: the browser keeps it.');
    }

    public function testOutsideOfAPanelASizeMadeWhenAskedForIsTheImage(): void
    {
        while ($this->kit->requests->pop() instanceof Request) {
            // Leaves no request of a panel.
        }

        self::assertSame('/lazy/ab/a.png', $this->kit->urls->url(FileReference::parse('lazy://ab/a.png'), 'thumbnail'));
    }

    public function testASizeOfASignedStorageIsItsDownload(): void
    {
        $url = (string) $this->kit->urls->url(FileReference::parse('private://ab/a.png'), 'preview');

        self::assertStringStartsWith('http://panel.example.com/admin/_files/download?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame(['private://ab/a.png', 'preview'], [$query['ref'], $query['variant']]);
        self::assertTrue($this->kit->signer->check($url));
    }

    public function testWithoutTheSizeItIsTheImage(): void
    {
        $urls = $this->kit->urls;

        self::assertSame('/plain/a.png', $urls->url(FileReference::parse('plain://a.png'), 'thumbnail'));
        self::assertSame('/uploads/a.svg', $urls->url(FileReference::parse('public://a.svg'), 'thumbnail'));
        self::assertSame('/uploads/a.png', $urls->url(FileReference::parse('public://a.png'), 'huge'));
        self::assertSame(
            'https://example.com/a.png',
            $urls->url(FileReference::parse('https://example.com/a.png'), 'thumbnail'),
        );
    }

    public function testTheAddressOfASizeIsOfTheImage(): void
    {
        $urls = $this->kit->urls;

        self::assertSame('public://ab/a.png', (string) $urls->referenceOf('/uploads/_variants/large/ab/a.png.webp'));
        self::assertSame('public://ab/a.png', (string) $urls->referenceOf('/uploads/_variants/preview/ab/a.png'));
        self::assertSame('public://ab/a.png', (string) $urls->referenceOf('/uploads/ab/a.png'));
    }

    public function testNoSizesWithoutThem(): void
    {
        $kit = new ImagesKit();
        $urls = new StorageFileUrls(
            $kit->storages,
            new ServiceLocator([]),
            new UrlGenerator(new RouteCollection(), new RequestContext()),
            $kit->signer,
            new RequestStack(),
        );

        self::assertSame('/uploads/a.png', $urls->url(FileReference::parse('public://a.png'), 'thumbnail'));
        self::assertSame(
            'public://_variants/preview/a.png',
            (string) $urls->referenceOf('/uploads/_variants/preview/a.png'),
        );
    }
}
