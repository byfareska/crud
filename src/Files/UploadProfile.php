<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use InvalidArgumentException;

/**
 * What a field takes: the storage an upload goes to, the storages its files may be picked from, the kinds of files
 * and how big. A field gives it to the browser in an address signed by the server (FilesController), so the browser
 * cannot change it.
 *
 * The kinds ("accept") are MIME types ("application/pdf"), groups ("image/*") or extensions (".pdf"); none = any
 * kind but those a browser would run as a page of the panel - HTML, SVG, scripts - which are taken only when named by
 * their MIME type.
 */
final readonly class UploadProfile
{
    /** The kinds of images of a gallery: those every browser shows. Not SVG, which can carry a script. */
    public const array IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

    /** The extensions of those images. */
    public const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];

    /** What a browser would run as a page of the panel: taken only when named in "accept" by its MIME type. */
    public const array ACTIVE = [
        'text/html',
        'application/xhtml+xml',
        'image/svg+xml',
        'text/xml',
        'application/xml',
        'text/javascript',
        'application/javascript',
        'application/x-httpd-php',
        'application/x-php',
        'text/x-php',
        'application/x-sh',
        'text/x-shellscript',
    ];

    /**
     * @param string $storage where an upload goes
     * @param list<string> $accept the kinds taken; empty = any but ACTIVE
     * @param int|null $maxSize in bytes; null = byfareska_crud.files.max_size
     * @param list<string> $browse the storages files may be picked from; empty = only $storage
     */
    public function __construct(
        public string $storage,
        public array $accept = [],
        public ?int $maxSize = null,
        public array $browse = [],
    ) {
        if (!FileReference::isStorageName($storage)) {
            throw new InvalidArgumentException(sprintf('"%s" is not the name of a storage.', $storage));
        }
    }

    /** The profile of a gallery: the images every browser shows. */
    public static function images(string $storage, ?int $maxSize = null): self
    {
        return new self($storage, self::IMAGES, $maxSize);
    }

    /**
     * "images" in a list of kinds stands for IMAGES.
     *
     * @param list<string> $accept
     * @return list<string>
     */
    public static function expand(array $accept): array
    {
        $kinds = [];
        foreach ($accept as $kind) {
            array_push($kinds, ...($kind === 'images' ? self::IMAGES : [strtolower(trim($kind))]));
        }

        return array_values(array_unique(array_filter($kinds, static fn (string $kind): bool => $kind !== '')));
    }

    /**
     * The storages files may be picked from, the one uploads go to first.
     *
     * @return list<string>
     */
    public function storages(): array
    {
        return array_values(array_unique([$this->storage, ...$this->browse]));
    }

    /** Whether it takes only images: a gallery. */
    public function imagesOnly(): bool
    {
        return $this->accept !== [] && array_all(
            $this->accept,
            static fn (string $kind): bool => str_starts_with($kind, 'image/')
                || in_array(ltrim($kind, '.'), self::IMAGE_EXTENSIONS, true),
        );
    }

    /**
     * @param string $mimeType what the file is, as read from its bytes
     * @param string $extension lower-case, without the dot
     */
    public function accepts(string $mimeType, string $extension): bool
    {
        $mimeType = strtolower($mimeType);
        if (in_array($mimeType, self::ACTIVE, true)) {
            return in_array($mimeType, $this->accept, true);
        }
        if ($this->accept === []) {
            return true;
        }

        foreach ($this->accept as $kind) {
            $matches = match (true) {
                str_starts_with($kind, '.') => $extension !== '' && substr($kind, 1) === $extension,
                str_ends_with($kind, '/*') => str_starts_with($mimeType, substr($kind, 0, -1)),
                default => $kind === $mimeType,
            };
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /** Whether a file of this kind may be picked for it (its MIME type known, or only its extension). */
    public function acceptsFile(StoredFile $file): bool
    {
        if ($file->mimeType !== null) {
            return $this->accepts($file->mimeType, $file->reference->extension());
        }

        // Without its MIME type: by the extension, as the kinds name it.
        $extension = $file->reference->extension();

        return $this->accept === [] || array_any(
            $this->accept,
            static fn (string $kind): bool => $kind === '.' . $extension
                || (str_starts_with($kind, 'image/') && in_array($extension, self::IMAGE_EXTENSIONS, true)),
        );
    }

    /**
     * What the address of an upload carries of it.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'storage' => $this->storage,
            'accept' => implode(',', $this->accept),
            'max' => $this->maxSize === null ? '' : (string) $this->maxSize,
            'browse' => implode(',', $this->browse),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * @param array<array-key, mixed> $query what toQuery() gave, read back from a signed address
     */
    public static function fromQuery(array $query): self
    {
        $text = static fn (string $key): string => is_string($query[$key] ?? null) ? $query[$key] : '';
        $list = static fn (string $key): array => array_values(array_filter(
            explode(',', $text($key)),
            static fn (string $item): bool => $item !== '',
        ));

        return new self(
            $text('storage'),
            $list('accept'),
            ctype_digit($text('max')) ? (int) $text('max') : null,
            $list('browse'),
        );
    }
}
