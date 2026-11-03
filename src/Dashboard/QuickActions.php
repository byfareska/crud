<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Byfareska\Crud\CommandPalette\CommandPaletteCatalog;
use Byfareska\Crud\CommandPalette\CommandPaletteSource;
use Byfareska\Crud\CommandPalette\PaletteEntry;
use Byfareska\Crud\Panel\Scopes;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The "new …" entries of the command palette (a page that adds a record), as the tiles of the quick actions: each
 * with the icon of the list it belongs to.
 */
final readonly class QuickActions
{
    private const string SUFFIX = '.create';

    /**
     * @param iterable<CommandPaletteSource> $sources
     */
    public function __construct(
        #[AutowireIterator(CommandPaletteSource::TAG)]
        private iterable $sources,
        private Scopes $scopes,
    ) {
    }

    /**
     * Every such entry, whoever may use it.
     *
     * @return array<string, array{entry: PaletteEntry, icon: string}> by key
     */
    public function all(): array
    {
        $entries = [];
        foreach ($this->sources as $source) {
            foreach ($source->entries() as $entry) {
                $entries[$entry->key] = $entry;
            }
        }

        $actions = [];
        foreach ($entries as $key => $entry) {
            if ($entry->routeName === null || (!$entry->quickAction && !str_ends_with($key, self::SUFFIX))) {
                continue;
            }
            $list = $entries[substr($key, 0, -strlen(self::SUFFIX)) . '.list'] ?? null;
            $actions[$key] = ['entry' => $entry, 'icon' => $list->icon ?? $entry->icon];
        }

        return $actions;
    }

    /**
     * Those the one acting may use: of $keys, or all when none is chosen - on the dashboard of $board, those of its
     * panel and of none (null = of every panel).
     *
     * @param list<string> $keys
     *
     * @return list<array{entry: PaletteEntry, icon: string}>
     */
    public function available(array $keys = [], ?string $board = null): array
    {
        $available = [];
        foreach ($this->all() as $key => $action) {
            if (
                ($keys === [] || in_array($key, $keys, true))
                && CommandPaletteCatalog::inScope($action['entry']->scope, $board)
                && $this->scopes->isGrantedIn($action['entry']->scope, $action['entry']->role)
                && ($action['entry']->offered === null || ($action['entry']->offered)())
            ) {
                $available[] = $action;
            }
        }

        return $available;
    }
}
