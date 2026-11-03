<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\CommandPalette;

use Byfareska\Crud\CommandPalette\PaletteItem;
use Byfareska\Crud\CommandPalette\TextPaletteSearch;
use PHPUnit\Framework\TestCase;

final class TextPaletteSearchTest extends TestCase
{
    public function testBestMatchesFirstThenTheOrderOfTheSources(): void
    {
        $items = $this->items([
            'orders' => ['Zamówienia', 'Lista zamówień', []],
            'new-order' => ['Nowe zamówienie', 'Dodaj zamówienie', []],
            'theme' => ['Przełącz motyw', 'Wygląd panelu', ['dark mode']],
            'users' => ['Użytkownicy', 'Konta', ['zamowienia klientow']],
        ]);

        // Without its accents, the start of a name first, then a part of one, then a keyword.
        self::assertSame(['orders', 'new-order', 'users'], new TextPaletteSearch()->search('zamow', $items, 'pl', 10));
        self::assertSame(['theme'], new TextPaletteSearch()->search('dark', $items, 'pl', 10));
        self::assertSame(['orders'], new TextPaletteSearch()->search('zamow', $items, 'pl', 1));
        self::assertSame([], new TextPaletteSearch()->search('faktury', $items, 'pl', 10));
    }

    /**
     * @param array<string, array{string, string, list<string>}> $texts
     *
     * @return array<string, PaletteItem>
     */
    private function items(array $texts): array
    {
        $items = [];
        foreach ($texts as $key => [$name, $description, $keywords]) {
            $items[$key] = new PaletteItem($key, 'group', $name, $description, 'box', keywords: $keywords);
        }

        return $items;
    }
}
