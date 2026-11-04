<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Panel\Scopes;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What the browser gets of files: their JSON (with the signature a field asks of a new reference) and the signed
 * address a field uploads to.
 */
final readonly class BrowserFiles
{
    /** For how long the address of an upload a page was drawn with works. */
    public const int PROFILE_TTL = 86400;

    public function __construct(
        private FileUrls $urls,
        private FileReferenceSigner $signer,
        private UriSigner $uriSigner,
        private UrlGeneratorInterface $router,
        private FileStorages $storages,
        private Scopes $scopes,
    ) {
    }

    /**
     * @return array{ref: string, sig: string, url: string|null, name: string, mime: string|null, size: int|null,
     *     width: int|null, height: int|null, image: bool}
     */
    public function json(StoredFile $file): array
    {
        return [
            'ref' => (string) $file->reference,
            'sig' => $this->signer->sign($file->reference),
            'url' => $this->urls->url($file->reference),
            'name' => $file->name,
            'mime' => $file->mimeType,
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
            'image' => $file->isImage(),
        ];
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
