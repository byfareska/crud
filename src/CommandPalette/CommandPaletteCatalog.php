<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

use Byfareska\Crud\Panel\Scopes;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Everything the CommandPaletteSources put into the command palette, with its texts in one language.
 */
final readonly class CommandPaletteCatalog
{
    /**
     * @param iterable<CommandPaletteSource> $sources
     */
    public function __construct(
        #[AutowireIterator(CommandPaletteSource::TAG)]
        private iterable $sources,
        private TranslatorInterface $translator,
        private Scopes $scopes,
    ) {
    }

    /**
     * @param bool $everything also what only some may use (an index is built for everybody); otherwise what the one
     *     asking is offered
     * @param string|null $scope the panel the palette is in: what is for it or for every panel; null = all
     *
     * @return array<string, PaletteItem> by key, in the order the sources list them
     */
    public function items(string $locale, bool $everything = false, ?string $scope = null): array
    {
        $items = [];
        foreach ($this->sources as $source) {
            foreach ($source->entries() as $entry) {
                if (!self::inScope($entry->scope, $scope)) {
                    continue;
                }
                // An entry of a panel is not for somebody the panel is not for.
                if ($everything || $this->isOffered($entry)) {
                    $items[$entry->key] = $this->itemOf($entry, $locale);
                }
            }
        }

        return $items;
    }

    /**
     * Whether the one asking is offered an entry: its panel, its role and what else it says (PaletteEntry::$offered).
     */
    public function isOffered(PaletteEntry $entry): bool
    {
        return $this->scopes->isGrantedIn($entry->scope, $entry->role)
            && (!$entry->offered instanceof \Closure || ($entry->offered)());
    }

    /**
     * Whether something of a panel ($of; null = of every one) belongs to the palette of $scope (null = every panel).
     */
    public static function inScope(?string $of, ?string $scope): bool
    {
        return $scope === null || $of === null || $of === $scope;
    }

    private function itemOf(PaletteEntry $entry, string $locale): PaletteItem
    {
        $keywords = array_map(trim(...), explode(',', $this->optional($entry->keywords, $locale)));

        return new PaletteItem(
            key: $entry->key,
            group: $entry->group,
            name: $entry->translate
                ? $this->translator->trans($entry->name, $entry->nameParameters, null, $locale)
                : $entry->name,
            description: $this->optional($entry->description, $locale),
            icon: $entry->icon,
            routeName: $entry->routeName,
            routeParameters: $entry->routeParameters,
            clientAction: $entry->clientAction,
            command: $entry->command,
            keywords: array_values(array_filter($keywords, static fn (string $keyword): bool => $keyword !== '')),
        );
    }

    /**
     * The text of a key that does not have to exist. Looked up in the catalogue first: a key translated without a text
     * is reported as missing (the profiler, the logs).
     */
    private function optional(?string $key, string $locale): string
    {
        if (
            $key === null
            || !$this->translator instanceof TranslatorBagInterface
            || !$this->translator->getCatalogue($locale)->has($key)
        ) {
            return '';
        }

        return $this->translator->trans($key, [], null, $locale);
    }
}
