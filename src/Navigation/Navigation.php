<?php

declare(strict_types=1);

namespace Byfareska\Crud\Navigation;

use Byfareska\Crud\Panel\Scopes;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The menus of the panels: the pages the NavigationSources declare ({@see NavigationSource}), in order; each panel
 * (route scope) has those of its own.
 */
final class Navigation implements ResetInterface
{
    /** @var list<NavigationItem>|null what items() found in this request */
    private ?array $items = null;

    /**
     * @param iterable<NavigationSource> $sources
     * @param iterable<NavigationExtension> $extensions
     */
    public function __construct(
        #[AutowireIterator(NavigationSource::TAG)]
        private readonly iterable $sources,
        private readonly Scopes $scopes,
        #[AutowireIterator(NavigationExtension::TAG)]
        private readonly iterable $extensions = [],
    ) {
    }

    public function reset(): void
    {
        $this->items = null;
    }

    /**
     * Every page, also those only some may open.
     *
     * @return list<NavigationItem> lowest priority first; pages of equal priority in the order they were declared
     */
    public function items(): array
    {
        // Once a request: the menu, the palette, the quick actions all ask - a source reading the database once.
        if ($this->items !== null) {
            return $this->items;
        }
        $items = [];
        foreach ($this->sources as $source) {
            foreach ($source->items() as $item) {
                $items[] = $item;
            }
        }

        // usort() is stable, so equal priorities keep their order.
        $byPriority = static fn (NavigationItem $a, NavigationItem $b): int => $a->priority <=> $b->priority;
        usort($items, $byPriority);
        if ($this->extensions !== []) {
            foreach ($this->extensions as $extension) {
                $items = $extension->extend($items);
            }
            usort($items, $byPriority);
        }

        return $this->items = $items;
    }

    /**
     * The pages of the menu of a panel the one looking at it may open, by the heading they are listed under.
     *
     * @param string|null $scope the panel (route scope); null = the pages of every panel
     *
     * @return array<string, list<NavigationItem>>
     */
    public function sections(?string $scope = null): array
    {
        $sections = [];
        foreach ($this->items() as $item) {
            if ($scope !== null && $item->scope !== $scope) {
                continue;
            }
            // A page of a panel is not for somebody the panel is not for.
            if ($this->scopes->isGrantedIn($item->scope, $item->role)) {
                $sections[$item->section][] = $item;
            }
        }

        return $sections;
    }
}
