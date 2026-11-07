<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Image;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileRoutes;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use Byfareska\Crud\Files\Image\ImageVariant;
use Byfareska\Crud\Files\Image\ImageVariants;
use Byfareska\Crud\Files\StorageFileUrls;
use Byfareska\Crud\Panel\Scope;
use Closure;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Storages in memory with the sizes of images, on the pages of the panel "admin": "public" (made on upload, under
 * /uploads), "lazy" (made when asked for), "private" (signed addresses), "plain" (no sizes). The sizes are the bundle's
 * and "large" (1600 wide, WebP), made by FakeImageResizer - no GD needed.
 */
final class ImagesKit
{
    public Filesystem $filesystem;

    /** @var array<string, Filesystem> */
    public array $filesystems = [];

    public FileStorages $storages;

    public ImageVariants $variants;

    public StorageFileUrls $urls;

    public UriSigner $signer;

    public RequestStack $requests;

    public MemoryLogger $logger;

    public FakeImageResizer $resizer;

    public function __construct(int $maxPixels = 40_000_000)
    {
        // A filesystem of its own for each storage; $filesystem is that of "public".
        foreach (['public', 'lazy', 'private', 'plain'] as $name) {
            $this->filesystems[$name] = new Filesystem(new InMemoryFilesystemAdapter());
        }
        $this->filesystem = $this->filesystems['public'];
        $this->storages = new FileStorages(
            [
                'public' => ['public_url' => '/uploads', 'variants' => 'eager'],
                'lazy' => ['public_url' => '/lazy', 'variants' => 'lazy'],
                'private' => ['url' => 'signed', 'variants' => 'eager'],
                'plain' => ['public_url' => '/plain'],
            ],
            new ServiceLocator(array_map(
                static fn (Filesystem $filesystem): Closure => static fn (): FlysystemFileStorage
                    => new FlysystemFileStorage($filesystem),
                $this->filesystems,
            )),
        );
        $this->logger = new MemoryLogger();
        $this->resizer = new FakeImageResizer();
        $this->variants = new ImageVariants(
            $this->storages,
            $this->resizer,
            [
                ...array_map(
                    ImageVariant::fromConfig(...),
                    array_keys(ImageVariant::DEFAULTS),
                    ImageVariant::DEFAULTS,
                ),
                new ImageVariant('large', 1600, format: 'webp', label: 'Large'),
            ],
            maxPixels: $maxPixels,
            logger: $this->logger,
        );

        $routes = new RouteCollection();
        $routes->add(FileRoutes::download('admin'), new Route('/admin/_files/download'));
        $routes->add(FileRoutes::variant('admin'), new Route('/admin/_files/variant'));
        $this->requests = new RequestStack();
        $this->requests->push(new Request(attributes: [Scope::ATTRIBUTE => 'admin']));
        $this->signer = new UriSigner('secret');
        $this->urls = new StorageFileUrls(
            $this->storages,
            new ServiceLocator([]),
            new UrlGenerator($routes, new RequestContext(host: 'panel.example.com')),
            $this->signer,
            $this->requests,
            variants: $this->variants,
        );
    }

    /** An image of these pixels written to a storage: a PNG of its header only, enough for getimagesize(). */
    public function image(string $reference, int $width = 1600, int $height = 1200): FileReference
    {
        $reference = FileReference::parse($reference);
        $this->filesystems[$reference->storage]->write($reference->path, FakeImageResizer::png($width, $height));

        return $reference;
    }

    public function has(FileReference $reference): bool
    {
        return $this->filesystems[$reference->storage]->fileExists($reference->path);
    }
}
