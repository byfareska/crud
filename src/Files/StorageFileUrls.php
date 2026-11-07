<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Files\Image\ImageVariants;
use Byfareska\Crud\Files\Image\VariantStrategy;
use Byfareska\Crud\Panel\Scope;
use DateTimeImmutable;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The addresses of files by the "url" of their storage (UrlMode): public, temporary (the storage's), signed (the
 * panel's download address) or none. A storage with "urls" has the addresses of that service instead. A file of
 * another site is its own address.
 *
 * A size of an image (byfareska_crud.files.images) is the address of its file in the storage when the storage makes
 * its sizes on upload (VariantStrategy::Eager), the panel's address that makes it when it is first asked for (Lazy),
 * the signed download of it for a storage of signed addresses; an image without that size is its own address.
 */
final readonly class StorageFileUrls implements FileUrls
{
    /**
     * @param ContainerInterface $own the FileUrls of the storages that name one ("urls"), by name
     * @param string|null $scope the panel whose download address a signed file gets outside of a panel; null = only
     *     on the pages of a panel
     * @param ImageVariants|null $variants the sizes of images; null = none (byfareska_crud.files.images disabled)
     */
    public function __construct(
        private FileStorages $storages,
        private ContainerInterface $own,
        private UrlGeneratorInterface $router,
        private UriSigner $signer,
        private RequestStack $requestStack,
        private ?string $scope = null,
        private ?ImageVariants $variants = null,
    ) {
    }

    public function url(FileReference $reference, ?string $variant = null): ?string
    {
        if ($reference->isExternal()) {
            return (string) $reference;
        }
        if (!$this->storages->has($reference->storage)) {
            return null;
        }
        if ($this->own->has($reference->storage)) {
            return $this->ownOf($reference->storage)->url($reference, $variant);
        }

        $settings = $this->storages->settings($reference->storage);
        if ($variant !== null && $this->variants?->applies($reference, $variant) === true) {
            return $this->variantUrl($this->variants, $reference, $variant, $settings);
        }

        return match ($settings->url) {
            UrlMode::Public => $settings->publicUrl !== null
                ? rtrim($settings->publicUrl, '/') . '/' . self::encodePath($reference->path)
                : $this->storages->storage($reference->storage)->publicUrl($reference->path),
            UrlMode::Temporary => $this->storages->storage($reference->storage)->temporaryUrl(
                $reference->path,
                new DateTimeImmutable(sprintf('+%d seconds', $settings->ttl)),
            ),
            UrlMode::Signed => $this->signed($reference, $settings->ttl),
            UrlMode::None => null,
        };
    }

    public function referenceOf(string $url): ?FileReference
    {
        foreach ($this->storages->names() as $name) {
            if ($this->own->has($name)) {
                $reference = $this->ownOf($name)->referenceOf($url);
                if ($reference instanceof FileReference) {
                    return $reference;
                }
                continue;
            }
            $settings = $this->storages->settings($name);
            if ($settings->url !== UrlMode::Public) {
                continue;
            }
            $base = $this->baseOf($name, $settings);
            if ($base !== null && $base !== '' && str_starts_with($url, $base)) {
                $path = rawurldecode(substr($url, strlen($base)));
                $reference = FileReference::tryParse($name . '://' . strtok($path, '?#'));

                // The address of a size is of the image.
                return $reference instanceof FileReference && $this->variants instanceof ImageVariants
                    ? $this->variants->originalOf($reference)
                    : $reference;
            }
        }

        return null;
    }

    /** Each part of a path encoded for an address, the slashes kept. */
    public static function encodePath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /**
     * The address of a size of an image: of its file, of the address of the panel that makes it, or of its download.
     */
    private function variantUrl(
        ImageVariants $variants,
        FileReference $reference,
        string $variant,
        StorageSettings $settings,
    ): ?string {
        return match (true) {
            $settings->url === UrlMode::None => null,
            $settings->url === UrlMode::Signed => $this->signed($reference, $settings->ttl, $variant),
            $settings->variants === VariantStrategy::Lazy => $this->lazy($reference, $variant)
                // Outside of a panel there is no address that makes it: the image itself.
                ?? $this->url($reference),
            default => $this->url($variants->referenceOf($reference, $variant)),
        };
    }

    /**
     * The panel's address that makes a size and sends the browser to its file: signed without an end, so it stays the
     * same and the browser keeps what it fetched.
     */
    private function lazy(FileReference $reference, string $variant): ?string
    {
        $scope = $this->currentScope();
        if ($scope === null) {
            return null;
        }
        try {
            $url = $this->router->generate(
                FileRoutes::variant($scope),
                ['ref' => (string) $reference, 'variant' => $variant],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } catch (RouteNotFoundException) {
            return null;
        }

        return $this->signer->sign($url);
    }

    private function currentScope(): ?string
    {
        $scope = $this->requestStack->getMainRequest()?->attributes->get(Scope::ATTRIBUTE);

        return is_string($scope) && $scope !== '' ? $scope : $this->scope;
    }

    /**
     * What the public addresses of a storage begin with.
     */
    private function baseOf(string $name, StorageSettings $settings): ?string
    {
        if ($settings->publicUrl !== null) {
            return rtrim($settings->publicUrl, '/') . '/';
        }
        // The storage's own: what it puts before a path.
        $probe = $this->storages->storage($name)->publicUrl('x');

        return $probe !== null && str_ends_with($probe, 'x') ? substr($probe, 0, -1) : null;
    }

    private function signed(FileReference $reference, int $ttl, ?string $variant = null): ?string
    {
        $scope = $this->currentScope();
        if ($scope === null) {
            return null;
        }
        try {
            $url = $this->router->generate(
                FileRoutes::download($scope),
                ['ref' => (string) $reference] + ($variant !== null ? ['variant' => $variant] : []),
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } catch (RouteNotFoundException) {
            return null;
        }

        return $this->signer->sign($url, new DateTimeImmutable('@' . self::expiresAt(time(), $ttl)));
    }

    /**
     * When a signed address made now stops working: at least $ttl seconds from now, rounded up to a quarter of it -
     * so the address stays the same for a while and the browser keeps what it fetched instead of fetching it again on
     * every page.
     */
    public static function expiresAt(int $now, int $ttl): int
    {
        $step = max(1, intdiv($ttl, 4));

        return intdiv($now + $ttl + $step - 1, $step) * $step;
    }

    private function ownOf(string $storage): FileUrls
    {
        $urls = $this->own->get($storage);
        if (!$urls instanceof FileUrls) {
            throw new \InvalidArgumentException(sprintf('The "urls" of the storage "%s" is no FileUrls.', $storage));
        }

        return $urls;
    }
}
