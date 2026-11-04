<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use InvalidArgumentException;

/**
 * A random name in random directories: `ab/cd/0123456789efghijklmnopqrstuvwx.jpg`. Lower-case letters and digits only
 * (a disk that does not tell "A" from "a" keeps them apart); 32 of them in the name are about 165 bits.
 *
 * Two levels of two characters (36^4, 1.7 million directories) keep a few files in each even with tens of millions of
 * them; more levels make a directory for nearly every file - on FTP a MKD each. On S3 and the like, where directories
 * are only prefixes, levels: 0. With keepName the name it was uploaded under follows, made plain:
 * `ab/cd/0123…-invoice-2026-10.pdf` - findable in a storage without a library of its own.
 */
final readonly class ShardedPathGenerator implements PathGenerator
{
    private const string ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';

    public function __construct(
        private int $levels = 2,
        private int $width = 2,
        private int $length = 32,
        private bool $keepName = false,
    ) {
        if ($levels < 0 || $width < 1 || $length < 8) {
            throw new InvalidArgumentException(
                'A sharded path has 0 or more levels of 1 or more characters, and a name of 8 or more.',
            );
        }
    }

    public function generate(string $storage, string $extension, string $name): string
    {
        $directories = [];
        for ($level = 0; $level < $this->levels; ++$level) {
            $directories[] = $this->random($this->width);
        }
        $random = $this->random($this->length);

        // Without its extension: the kind it is gives one of its own.
        $slug = $this->keepName ? self::slug((string) preg_replace('/\.[^.\/]*$/', '', $name)) : '';
        $file = $random . ($slug === '' ? '' : '-' . $slug) . ($extension === '' ? '' : '.' . $extension);

        return implode('/', [...$directories, $file]);
    }

    private function random(int $length): string
    {
        $random = '';
        for ($i = 0; $i < $length; ++$i) {
            $random .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $random;
    }

    /**
     * Lower-case ASCII letters, digits and dashes, at most 60 of them.
     */
    public static function slug(string $text): string
    {
        if (function_exists('transliterator_transliterate')) {
            $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');

        return rtrim(substr($slug, 0, 60), '-');
    }
}
