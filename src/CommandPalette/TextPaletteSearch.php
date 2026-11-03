<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * Finds entries by their texts letter by letter ({@see TextMatcher}): what starts with what was typed first, then
 * what has it in its name, then what has every typed word somewhere in its name, description or keywords. Equal
 * matches keep the order of the catalog.
 */
final readonly class TextPaletteSearch implements PaletteSearch
{
    public function __construct(
        private TextMatcher $matcher = new TextMatcher(),
    ) {
    }

    public function search(string $query, array $items, string $locale, int $limit): array
    {
        $scored = [];
        foreach (array_values($items) as $position => $item) {
            $score = $this->matcher->score($item, $query);
            if ($score > 0) {
                $scored[] = [$score, $position, $item->key];
            }
        }

        usort($scored, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_slice(array_column($scored, 2), 0, $limit);
    }
}
