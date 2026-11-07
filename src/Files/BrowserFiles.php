<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Files\Image\ImageVariant;
use Byfareska\Crud\Files\Image\ImageVariants;
use Byfareska\Crud\Files\Image\VariantStrategy;
use Byfareska\Crud\Panel\Scopes;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * What the browser gets of files: their JSON (with the signature a field asks of a new reference) and the signed
 * address a field uploads to.
 */
final readonly class BrowserFiles
{
    /** For how long the address of an upload a page was drawn with works. */
    public const int PROFILE_TTL = 86400;

    /** The longest alternative text a file is given by its name. */
    private const int ALT_LENGTH = 125;

    private SluggerInterface $slugger;

    public function __construct(
        private FileUrls $urls,
        private FileReferenceSigner $signer,
        private UriSigner $uriSigner,
        private UrlGeneratorInterface $router,
        private FileStorages $storages,
        private Scopes $scopes,
        ?SluggerInterface $slugger = null,
        private ?ImageVariants $variants = null,
    ) {
        $this->slugger = $slugger ?? new AsciiSlugger();
    }

    /**
     * "alt": the alternative text a field gives a file it takes in (when it asks for one and none is typed) - the name
     * it was uploaded under, made plain. "preview": the address of its size "preview" (the image itself without
     * one); "sizes": the sizes of an image of a storage that makes them on upload, which a field of rich text offers.
     *
     * @return array{ref: string, sig: string, url: string|null, preview: string|null, name: string, alt: string,
     *     mime: string|null, size: int|null, width: int|null, height: int|null, image: bool,
     *     sizes: array<string, array{url: string|null, width: int|null, height: int|null}>}
     */
    public function json(StoredFile $file): array
    {
        $url = $this->urls->url($file->reference);

        return [
            'ref' => (string) $file->reference,
            'sig' => $this->signer->sign($file->reference),
            'url' => $url,
            'preview' => $this->variants instanceof ImageVariants
                ? $this->urls->url($file->reference, ImageVariant::PREVIEW)
                : $url,
            'name' => $file->name,
            'alt' => $this->alt($file),
            'mime' => $file->mimeType,
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
            'image' => $file->isImage(),
            'sizes' => $this->sizes($file),
        ];
    }

    /**
     * The sizes of an image of a storage that makes them on upload: their addresses last, so a rich text may keep them.
     *
     * @return array<string, array{url: string|null, width: int|null, height: int|null}>
     */
    public function sizes(StoredFile $file): array
    {
        $variants = $this->variants;
        if (
            !$variants instanceof ImageVariants
            || $variants->strategy($file->reference->storage) !== VariantStrategy::Eager
        ) {
            return [];
        }
        $sizes = [];
        foreach (array_keys($variants->all()) as $name) {
            if ($variants->applies($file->reference, $name)) {
                [$width, $height] = $variants->dimensionsOf($file, $name);
                $sizes[$name] = [
                    'url' => $this->urls->url($file->reference, $name),
                    'width' => $width,
                    'height' => $height,
                ];
            }
        }

        return $sizes;
    }

    /**
     * The name a file was uploaded under, without its extension, in letters, digits and spaces: "Kot na płocie (2).jpg"
     * is "Kot na plocie 2". None for a file known only by its random name (a library that lists its storage).
     */
    public function alt(StoredFile $file): string
    {
        if ($file->name === basename($file->reference->path)) {
            return '';
        }
        // Not pathinfo(): a "/" of the name ("A/B testy.png") is no directory.
        $name = (string) preg_replace('/\.[^.]*$/', '', $file->name);
        $alt = $this->slugger->slug($name, ' ')->toString();

        return rtrim(mb_substr($alt, 0, self::ALT_LENGTH));
    }

    /**
     * The signed address the files of a profile are uploaded to, on the pages of a panel; null = nowhere (no such
     * storage, one only to pick from, one the user may not use, not on a page of a panel).
     */
    public function uploadUrl(UploadProfile $profile, ?string $scope = null): ?string
    {
        if (!$this->mayUse($profile->storage) || !$this->storages->settings($profile->storage)->upload) {
            return null;
        }
        $scope ??= $this->currentScope();
        if ($scope === null) {
            return null;
        }
        try {
            // Absolute: what the controller checks is the whole address of the request (UriSigner::checkRequest()).
            $url = $this->router->generate(
                FileRoutes::upload($scope),
                $profile->toQuery(),
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        } catch (RouteNotFoundException) {
            return null;
        }

        return $this->uriSigner->sign($url, new DateTimeImmutable(sprintf('+%d seconds', self::PROFILE_TTL)));
    }

    /**
     * What the window of the library is opened with for a field: its profile and whether it takes many files, signed -
     * the browser cannot widen it.
     */
    public function libraryToken(UploadProfile $profile, bool $multiple): string
    {
        $query = $profile->toQuery() + ['multiple' => $multiple ? '1' : '0'];

        return $this->uriSigner->sign('/?' . http_build_query($query));
    }

    /**
     * The profile and "multiple" of a token of libraryToken(); null = not one the server signed.
     *
     * @return array{UploadProfile, bool}|null
     */
    public function libraryOf(string $token): ?array
    {
        if ($token === '' || !str_starts_with($token, '/?') || !$this->uriSigner->check($token)) {
            return null;
        }
        parse_str((string) parse_url($token, PHP_URL_QUERY), $query);
        try {
            return [UploadProfile::fromQuery($query), ($query['multiple'] ?? '0') === '1'];
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** Whether the one acting may upload to a storage and pick its files. */
    public function mayUse(string $storage): bool
    {
        return $this->storages->has($storage)
            && $this->scopes->isGrantedIn($this->currentScope(), $this->storages->settings($storage)->role);
    }

    private function currentScope(): ?string
    {
        try {
            return $this->scopes->current()->name;
        } catch (\Throwable) {
            return null;
        }
    }
}
