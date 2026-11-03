<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\CommandPalette;

use Byfareska\Crud\CommandPalette\BalancedPaletteRanker;
use Byfareska\Crud\CommandPalette\PaletteItem;
use PHPUnit\Framework\TestCase;

final class BalancedPaletteRankerTest extends TestCase
{
    public function testTheEntriesKeepHalfOfThePlacesWhenThereAreThatMany(): void
    {
        $ranked = new BalancedPaletteRanker()->rank($this->items('page', 10), $this->items('record', 15), 14);

        self::assertCount(14, $ranked);
        $pages = array_filter($ranked, static fn (PaletteItem $item): bool => str_starts_with($item->key, 'page'));
        self::assertCount(7, $pages);
        self::assertSame('page0', $ranked[0]->key);
    }

    public function testWhatOneSideLeavesFreeTheOtherTakes(): void
    {
        $ranker = new BalancedPaletteRanker();

        self::assertCount(12, array_filter(
            $ranker->rank($this->items('page', 2), $this->items('record', 15), 14),
            static fn (PaletteItem $item): bool => str_starts_with($item->key, 'record'),
        ));
        self::assertCount(13, $ranker->rank($this->items('page', 13), $this->items('record', 0), 14));
        self::assertCount(14, $ranker->rank($this->items('page', 20), $this->items('record', 1), 14));
    }

    /**
     * @return list<PaletteItem>
     */
    private function items(string $prefix, int $count): array
    {
        return array_map(
            static fn (int $index): PaletteItem => new PaletteItem($prefix . $index, 'group', 'name', '', 'icon'),
            $count === 0 ? [] : range(0, $count - 1),
        );
    }
}
