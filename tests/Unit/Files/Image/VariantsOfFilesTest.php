<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Image;

use Byfareska\Crud\Controller\FilesController;
use Byfareska\Crud\Event\FileUploaded;
use Byfareska\Crud\Files\FileDownload;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileQuery;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileRemover;
use Byfareska\Crud\Files\FileUploader;
use Byfareska\Crud\Files\Image\ImageVariants;
use Byfareska\Crud\Files\Image\ImageVariantsListener;
use Byfareska\Crud\Files\NoFileLibrary;
use Byfareska\Crud\Files\NoFileUsages;
use Byfareska\Crud\Files\ShardedPathGenerator;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\UploadProfile;
use Byfareska\Crud\Form\CrudRichTextType;
use Byfareska\Crud\Tests\Unit\Files\FilesKit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The sizes of images where files are: the field and the library, rich text, deleting, the download and the address
 * of the panel that makes a size.
 */
final class VariantsOfFilesTest extends TestCase
{
    public function testWhatTheBrowserGetsOfAnImage(): void
    {
        $kit = new FilesKit(variants: 'eager');
        $image = new StoredFile(FileReference::parse('public://ab/a.png'), 'Kot.png', 'image/png', 10, 1600, 1200);

        $json = $kit->browser->json($image);

        self::assertSame('/uploads/ab/a.png', $json['url']);
        self::assertSame('/uploads/_variants/preview/ab/a.png', $json['preview']);
        self::assertSame(
            [
                'thumbnail' => ['url' => '/uploads/_variants/thumbnail/ab/a.png', 'width' => 96, 'height' => 96],
                'preview' => ['url' => '/uploads/_variants/preview/ab/a.png', 'width' => 480, 'height' => 360],
                'large' => ['url' => '/uploads/_variants/large/ab/a.png.webp', 'width' => 1600, 'height' => 1200],
            ],
            $json['sizes'],
        );
    }

    public function testWithoutSizesThePreviewIsTheImage(): void
    {
        $json = new FilesKit()->browser->json(new StoredFile(FileReference::parse('public://ab/a.png'), 'a.png'));

        self::assertSame('/uploads/ab/a.png', $json['preview']);
        self::assertSame([], $json['sizes']);
    }

    public function testASizeMadeWhenAskedForIsOfferedToNoRichText(): void
    {
        $kit = new FilesKit(variants: 'lazy');

        self::assertSame([], $kit->browser->sizes(new StoredFile(FileReference::parse('public://a.png'), 'a.png')));
    }

    public function testAnUploadedImageGetsItsSizes(): void
    {
        $kit = new FilesKit(variants: 'eager');
        $variants = $this->variantsOf($kit);
        $stored = $this->upload($kit);

        new ImageVariantsListener($variants)->uploaded(new FileUploaded($stored, UploadProfile::images('public')));

        self::assertSame(['thumbnail image/png', 'preview image/png', 'large image/webp'], $kit->resizer->made);
        self::assertTrue($variants->exists($stored->reference, 'large'));
    }

    public function testAStorageMakingThemWhenAskedForMakesNoneOnUpload(): void
    {
        $kit = new FilesKit(variants: 'lazy');
        $stored = $this->upload($kit);

        new ImageVariantsListener($this->variantsOf($kit))
            ->uploaded(new FileUploaded($stored, UploadProfile::images('public')));

        self::assertSame([], $kit->resizer->made);
    }

    public function testTheSizesAreNoFilesOfTheLibrary(): void
    {
        $kit = new FilesKit(variants: 'eager');
        $image = $kit->put('ab/a.png', FakeImageResizer::png(800, 600));
        $this->variantsOf($kit)->makeAll($image);

        $files = $kit->library->browse(new FileQuery('public'))->files;

        self::assertSame(['public://ab/a.png'], array_map(
            static fn (StoredFile $file): string => (string) $file->reference,
            $files,
        ));
    }

    public function testTheSizesAreDeletedWithTheImage(): void
    {
        $kit = new FilesKit(variants: 'eager');
        $variants = $this->variantsOf($kit);
        $image = $kit->put('ab/a.png', FakeImageResizer::png(800, 600));
        $variants->makeAll($image);

        new FileRemover($kit->storages, $kit->libraries, new NoFileUsages(), null, $variants)->remove($image);

        self::assertFalse($kit->filesystem->fileExists('ab/a.png'));
        foreach (['thumbnail', 'preview', 'large'] as $name) {
            self::assertFalse($variants->exists($image, $name), $name);
        }
    }

    public function testRichTextUsesTheImageOfASize(): void
    {
        $kit = new FilesKit(variants: 'eager');

        $references = $kit->richText->references(
            '<p><img src="/uploads/_variants/large/ab/a.png.webp"> <img src="/uploads/ab/a.png"></p>',
        );

        self::assertSame(['public://ab/a.png'], array_map(strval(...), $references));
    }

    public function testRichTextOffersTheSizesOfTheApplication(): void
    {
        $kit = new FilesKit(variants: 'eager');
        $kit->put('ab/a.png', FakeImageResizer::png(800, 600));

        $view = $kit->richText->view(
            $kit->richText->profile([]),
            ['size' => 'large'],
            '<p><img src="/uploads/_variants/large/ab/a.png.webp"></p>',
        );

        $sizes = $view['sizes'];
        self::assertNotNull($sizes);
        self::assertSame('file.size.label', $sizes['label']);
        self::assertSame('large', $sizes['initial']);
        // Not the bundle's thumbnail and preview, unless the field names them.
        self::assertSame([['name' => 'large', 'label' => 'Large']], $sizes['choices']);
        self::assertCount(1, $sizes['images']);
        self::assertSame('/uploads/ab/a.png', $sizes['images'][0]['url']);
        self::assertSame(['large'], array_keys($sizes['images'][0]['sizes']));
    }

    public function testAFieldNamesTheSizesItOffers(): void
    {
        $kit = new FilesKit(variants: 'eager');

        $view = $kit->richText->view($kit->richText->profile([]), ['sizes' => ['preview', 'thumbnail']]);

        self::assertNotNull($view['sizes']);
        // No translation of file.size.<name> here: the name.
        self::assertSame(
            [['name' => 'preview', 'label' => 'Preview'], ['name' => 'thumbnail', 'label' => 'Thumbnail']],
            $view['sizes']['choices'],
        );
        self::assertNull($kit->richText->view($kit->richText->profile([]), ['sizes' => []])['sizes']);
    }

    public function testASizeThereIsNotIsAMistakeOfTheField(): void
    {
        $kit = new FilesKit(variants: 'eager');

        $this->expectException(InvalidOptionsException::class);
        $kit->forms(['files' => true])->create(CrudRichTextType::class, null, ['files' => ['size' => 'huge']]);
    }

    public function testSizesOnlyWithThem(): void
    {
        $kit = new FilesKit();

        self::assertNull($kit->richText->view($kit->richText->profile([]))['sizes']);
        $this->expectException(InvalidOptionsException::class);
        $kit->richText->sizesOf(['sizes' => ['large']]);
    }

    public function testTheDownloadOfASize(): void
    {
        $kit = new ImagesKit();
        $image = $kit->image('private://ab/a.png', 800, 600);
        $download = new FileDownload($kit->storages, $this->librariesOf($kit), $kit->variants);

        $response = $download->response($image, 60, 'preview');

        self::assertNotNull($response);
        self::assertSame('image/png', $response->headers->get('Content-Type'));
        self::assertTrue($kit->variants->exists($image, 'preview'));
        ob_start();
        $response->sendContent();
        $size = getimagesizefromstring((string) ob_get_clean());
        self::assertNotFalse($size);
        self::assertSame([480, 360], [$size[0], $size[1]]);
    }

    public function testThePanelMakesASizeWhenAskedFor(): void
    {
        $kit = new FilesKit(variants: 'lazy');
        $image = $kit->put('ab/a.png', FakeImageResizer::png(800, 600));
        $url = (string) $kit->urls->url($image, 'thumbnail');

        $response = $this->controller($kit)->variant(Request::create($url));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/uploads/_variants/thumbnail/ab/a.png', $response->getTargetUrl());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame(['thumbnail image/png'], $kit->resizer->made);

        // Made once: the next one is sent to it.
        $this->controller($kit)->variant(Request::create($url));
        self::assertCount(1, $kit->resizer->made);
    }

    public function testAnImageWithoutTheSizeIsSentToItself(): void
    {
        $kit = new FilesKit(variants: 'lazy');
        $kit->put('ab/a.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $url = $kit->uriSigner->sign('http://localhost/admin/_files/variant?' . http_build_query([
            'ref' => 'public://ab/a.svg',
            'variant' => 'preview',
        ]));

        $response = $this->controller($kit)->variant(Request::create($url));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/uploads/ab/a.svg', $response->getTargetUrl());
    }

    public function testOnlyASignedAddressMakesASize(): void
    {
        $kit = new FilesKit(variants: 'lazy');
        $kit->put('ab/a.png', FakeImageResizer::png(800, 600));

        $this->expectException(AccessDeniedHttpException::class);
        $this->controller($kit)->variant(Request::create(
            'http://localhost/admin/_files/variant?ref=public://ab/a.png&variant=thumbnail',
        ));
    }

    public function testNoSizeWhenAskedForOfAStorageMakingThemOnUpload(): void
    {
        $kit = new FilesKit(variants: 'eager');
        $kit->put('ab/a.png', FakeImageResizer::png(800, 600));
        $url = $kit->uriSigner->sign('http://localhost/admin/_files/variant?' . http_build_query([
            'ref' => 'public://ab/a.png',
            'variant' => 'preview',
        ]));

        $this->expectException(NotFoundHttpException::class);
        $this->controller($kit)->variant(Request::create($url));
    }

    private function controller(FilesKit $kit): FilesController
    {
        return new FilesController(
            $kit->uriSigner,
            new FileUploader($kit->storages, $kit->libraries, new ShardedPathGenerator()),
            $kit->storages,
            $kit->browser,
            new FileDownload($kit->storages, $kit->libraries, $kit->variants),
            new IdentityTranslator(),
            $kit->urls,
            variants: $kit->variants,
        );
    }

    private function upload(FilesKit $kit): StoredFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'crud');
        file_put_contents($path, FakeImageResizer::png(800, 600));
        try {
            return new FileUploader($kit->storages, $kit->libraries, new ShardedPathGenerator())
                ->upload(new File($path), UploadProfile::images('public'), 'a.png');
        } finally {
            @unlink($path);
        }
    }

    private function variantsOf(FilesKit $kit): ImageVariants
    {
        self::assertInstanceOf(ImageVariants::class, $kit->variants);

        return $kit->variants;
    }

    private function librariesOf(ImagesKit $kit): FileLibraries
    {
        return new FileLibraries(new ServiceLocator(array_fill_keys(
            $kit->storages->names(),
            static fn (): NoFileLibrary => new NoFileLibrary(),
        )));
    }
}
