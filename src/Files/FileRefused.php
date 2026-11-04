<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use RuntimeException;

/**
 * An upload that is not taken: too big, of a kind the field does not take, refused by a listener of FileUploading.
 * Its message is a translation key (file.refused.*) with its parameters, shown to the one who uploaded.
 */
final class FileRefused extends RuntimeException
{
    /**
     * @param array<string, string|int> $parameters of the translation
     */
    public function __construct(
        public readonly string $key,
        public readonly array $parameters = [],
    ) {
        parent::__construct($key);
    }

    public static function tooBig(int $max): self
    {
        return new self('file.refused.too_big', ['max' => self::size($max)]);
    }

    /** A size as it is read: "512 KB", "10 MB", "1.5 GB". */
    public static function size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            ++$unit;
        }

        return rtrim(rtrim(number_format($size, $unit === 0 ? 0 : 1, '.', ''), '0'), '.') . ' ' . $units[$unit];
    }

    public static function kind(string $mimeType): self
    {
        return new self('file.refused.kind', ['kind' => $mimeType]);
    }

    public static function notUploaded(): self
    {
        return new self('file.refused.not_uploaded');
    }

    public static function storage(string $storage): self
    {
        return new self('file.refused.storage', ['storage' => $storage]);
    }
}
