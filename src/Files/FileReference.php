<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use InvalidArgumentException;
use Stringable;

/**
 * Where a file is: the storage it is in and its path there, written "<storage>://<path>" -
 * `public://ab/cd/0123456789abcdefghijklmnopqrstuv.jpg`. What a record keeps of a file: a column, a JSON list, an
 * attribute in HTML.
 *
 * The name of a storage is a part of the data, as a column name is: renaming one is a migration. The schemes of
 * addresses (http, https, ftp...) are no storage's: `https://cdn.example.com/a.jpg` is a file of another site, shown
 * but never written nor deleted.
 */
final readonly class FileReference implements Stringable
{
    /** What the name of a storage looks like. */
    public const string STORAGE_NAME = '/^[a-z][a-z0-9_-]*$/';

    /** Schemes of addresses: no storage may be named so. */
    public const array RESERVED = [
        'http',
        'https',
        'ftp',
        'ftps',
        'sftp',
        'file',
        'data',
        'blob',
        'mailto',
        'javascript',
    ];

    /** The schemes of a file of another site, which a reference may name. */
    public const array EXTERNAL = ['http', 'https'];

    /**
     * @param string $storage the name of the storage, or "http" / "https" for a file of another site
     * @param string $path within the storage, without a leading "/"; for a file of another site what follows "://"
     */
    public function __construct(
        public string $storage,
        public string $path,
    ) {
        if (!in_array($storage, self::EXTERNAL, true) && !self::isStorageName($storage)) {
            throw new InvalidArgumentException(sprintf('"%s" is not the name of a storage.', $storage));
        }
        if (!self::isSafePath($path)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a path of a file in a storage.', $path));
        }
    }

    /**
     * @throws InvalidArgumentException not a reference
     */
    public static function parse(string $reference): self
    {
        return self::tryParse($reference)
            ?? throw new InvalidArgumentException(sprintf('"%s" is not a reference to a file.', $reference));
    }

    public static function tryParse(string $reference): ?self
    {
        $at = strpos($reference, '://');
        if ($at === false) {
            return null;
        }
        $storage = substr($reference, 0, $at);
        $path = substr($reference, $at + 3);
        if (
            !in_array($storage, self::EXTERNAL, true) && !self::isStorageName($storage)
            || !self::isSafePath($path)
        ) {
            return null;
        }

        return new self($storage, $path);
    }

    /** Whether a storage may be named so: lower-case, and no scheme of addresses. */
    public static function isStorageName(string $name): bool
    {
        return preg_match(self::STORAGE_NAME, $name) === 1 && !in_array($name, self::RESERVED, true);
    }

    /** A file of another site: shown by its address, never written nor deleted. */
    public function isExternal(): bool
    {
        return in_array($this->storage, self::EXTERNAL, true);
    }

    /** The name of the file: the last part of its path. */
    public function basename(): string
    {
        $path = $this->isExternal() ? (string) parse_url('x://' . $this->path, PHP_URL_PATH) : $this->path;

        return basename($path);
    }

    /** Lower-case, without the dot; "" for none. */
    public function extension(): string
    {
        return strtolower(pathinfo($this->basename(), PATHINFO_EXTENSION));
    }

    public function equals(self $other): bool
    {
        return $this->storage === $other->storage && $this->path === $other->path;
    }

    public function __toString(): string
    {
        return $this->storage . '://' . $this->path;
    }

    /**
     * No way out of the storage nor into its root: no "..", no leading "/", no backslash, no control character.
     */
    private static function isSafePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || preg_match('/[\x00-\x1f\x7f\\\\]/', $path) === 1) {
            return false;
        }

        return !in_array('..', explode('/', $path), true);
    }
}
