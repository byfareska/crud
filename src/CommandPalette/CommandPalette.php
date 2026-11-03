<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

use Byfareska\Crud\Panel\Scopes;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

/**
 * What the command palette lists for what was typed: the entries its {@see PaletteSearch} finds, then the stored
 * records the {@see CommandPaletteRecordSource}s find. With nothing typed: what was chosen recently, then the
 * suggested entries (byfareska_crud.command_palette.suggested; by default the menu of the panel).
 */
final readonly class CommandPalette
{
    public const string GROUP_RECENT = 'panel.palette.group.recent';
    public const string GROUP_SUGGESTED = 'panel.palette.group.suggested';

    /**
     * @param iterable<CommandPaletteRecordSource> $recordSources
     * @param list<string> $suggested keys of entries
     */
    public function __construct(
        private CommandPaletteCatalog $catalog,
        private PaletteSearch $search,
        #[AutowireIterator(CommandPaletteRecordSource::TAG)]
        private iterable $recordSources,
        private Scopes $scopes,
        #[Autowire(param: 'byfareska_crud.command_palette.suggested')]
        private array $suggested = [],
        private LoggerInterface $logger = new NullLogger(),
        private PaletteRanker $ranker = new BalancedPaletteRanker(),
        #[Autowire(param: 'byfareska_crud.command_palette.records_per_source')]
        private int $recordsPerSource = 5,
    ) {
    }

    /**
     * @param string $query what was typed
     * @param bool $commandsOnly only the commands (what was typed began with ">")
     * @param list<string> $recent keys of what was chosen lately, the latest first
     * @param string|null $scope the panel the palette is in
     *
     * @return list<PaletteItem>
     */
    public function search(
        string $query,
        string $locale,
        int $limit,
        bool $commandsOnly = false,
        array $recent = [],
        ?string $scope = null,
    ): array {
        $query = trim($query);
        $items = $this->catalog->items($locale, scope: $scope);
        if ($commandsOnly) {
            $items = array_filter($items, static fn (PaletteItem $item): bool => $item->command);
        }

        if ($query === '') {
            return $commandsOnly ? array_slice(array_values($items), 0, $limit) : $this->idle($items, $recent, $limit);
        }

        // Records are looked for only by somebody who did not ask for commands.
        $records = $commandsOnly ? [] : array_slice($this->records($query, $scope), 0, $limit);
        $found = [];
        foreach ($this->search->search($query, $items, $locale, $limit) as $key) {
            if (isset($items[$key])) {
                $found[] = $items[$key];
            }
        }

        return $this->ranker->rank($found, $records, $limit);
    }

    /**
     * Before anything is typed: recently chosen items first, then suggestions that are not listed already.
     *
     * @param array<string, PaletteItem> $items
     * @param list<string> $recent
     *
     * @return list<PaletteItem>
     */
    private function idle(array $items, array $recent, int $limit): array
    {
        $listed = [];
        foreach ($recent as $key) {
            if (isset($items[$key])) {
                $listed[$key] = $items[$key]->inGroup(self::GROUP_RECENT);
            }
        }
        $listed = array_slice($listed, 0, $limit, true);

        $suggested = $this->suggested === []
            ? array_filter($items, static fn (PaletteItem $item): bool => !$item->command)
            : array_filter(array_map(static fn (string $key): ?PaletteItem => $items[$key] ?? null, $this->suggested));
        foreach ($suggested as $item) {
            $listed[$item->key] ??= $item->inGroup(self::GROUP_SUGGESTED);
        }

        return array_slice(array_values($listed), 0, $limit);
    }

    /**
     * @return list<PaletteItem>
     */
    private function records(string $query, ?string $scope): array
    {
        $records = [];
        foreach ($this->recordSources as $source) {
            try {
                // Told the panel, a source looks only there.
                $found = $source instanceof ScopedRecordSource
                    ? $source->searchIn($query, $this->recordsPerSource, $scope)
                    : $source->search($query, $this->recordsPerSource);
            } catch (Throwable $exception) {
                // One source that fails takes nothing away from the others.
                $this->logger->warning(
                    'A source of records of the command palette failed: {reason}',
                    ['reason' => $exception->getMessage(), 'exception' => $exception],
                );

                continue;
            }
            foreach ($found as $record) {
                // Whatever a source finds, a record of a panel is not for somebody the panel is not for.
                if (
                    CommandPaletteCatalog::inScope($record->scope, $scope)
                    && $this->scopes->isGrantedIn($record->scope)
                ) {
                    $records[] = new PaletteItem(
                        key: $record->key,
                        group: $record->group,
                        name: $record->name,
                        description: $record->description,
                        icon: $record->icon,
                        routeName: $record->routeName,
                        routeParameters: $record->routeParameters,
                        remembered: false,
                    );
                }
            }
        }

        return $records;
    }
}
