<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

use Normalizer;

/**
 * Matches what was typed against the texts of an item letter by letter - what finds an item while its name is
 * still being typed. Also a fallback for a search by meaning, when its index cannot be asked.
 */
final readonly class TextMatcher
{
    /**
     * @return int 0 when the item does not match; the higher, the better the match
     */
    public function score(PaletteItem $item, string $query): int
    {
        $needle = $this->fold($query);
        if ('' === $needle) {
            return 1;
        }

        $name = $this->fold($item->name);
        if (str_starts_with($name, $needle)) {
            return 100;
        }

        if (str_contains($name, $needle)) {
            return 60;
        }

        $haystack = $this->fold(implode(' ', [
            $item->name,
            $item->description,
            ...$item->keywords,
        ]));

        $everyWord = array_all(explode(' ', $needle), static fn (string $word): bool => str_contains($haystack, $word));

        return $everyWord ? 30 : 0;
    }

    /**
     * Lower-cases, strips diacritics and collapses whitespace, so "zamowienia" matches "Zamówienia".
     */
    private function fold(string $text): string
    {
        $lower = mb_strtolower($text);
        // Without intl the letters are matched as they are.
        $decomposed = class_exists(Normalizer::class) ? Normalizer::normalize($lower, Normalizer::FORM_D) : $lower;
        $unaccented = (string) preg_replace('/\p{Mn}+/u', '', \is_string($decomposed) ? $decomposed : $lower);

        return trim((string) preg_replace('/\s+/u', ' ', str_replace('ł', 'l', $unaccented)));
    }
}
