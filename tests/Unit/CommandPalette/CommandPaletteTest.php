<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\CommandPalette;

use Byfareska\Crud\CommandPalette\CommandPalette;
use Byfareska\Crud\CommandPalette\CommandPaletteCatalog;
use Byfareska\Crud\CommandPalette\CommandPaletteRecordSource;
use Byfareska\Crud\CommandPalette\PaletteSearch;
use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Tests\Unit\Fixtures\RecordingActor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * What was typed is compared with every entry and searched for by every source of records: as much of it as a
 * person types, not the megabytes a request can carry.
 */
final class CommandPaletteTest extends TestCase
{
    public function testOnlyTheBeginningOfALongTextIsLookedFor(): void
    {
        $search = new class () implements PaletteSearch {
            /** @var list<string> */
            public array $queries = [];

            public function search(string $query, array $items, string $locale, int $limit): array
            {
                $this->queries[] = $query;

                return [];
            }
        };
        $records = new class () implements CommandPaletteRecordSource {
            /** @var list<string> */
            public array $queries = [];

            public function search(string $query, int $limit): array
            {
                $this->queries[] = $query;

                return [];
            }
        };
        $requests = new RequestStack();
        $scopes = new Scopes([], 'layout.html.twig', 'daisyui', [], $requests, new RecordingActor([]), new CurrentCrud(
            $requests,
        ));
        $palette = new CommandPalette(
            new CommandPaletteCatalog([], new IdentityTranslator(), $scopes),
            $search,
            [$records],
            $scopes,
        );

        $palette->search('  ' . str_repeat('ż', CommandPalette::MAX_QUERY) . str_repeat('x', 1_000_000), 'pl', 14);
        $palette->search('faktura 7', 'pl', 14);

        $expected = [str_repeat('ż', CommandPalette::MAX_QUERY), 'faktura 7'];
        self::assertSame($expected, $search->queries);
        self::assertSame($expected, $records->queries);
    }
}
