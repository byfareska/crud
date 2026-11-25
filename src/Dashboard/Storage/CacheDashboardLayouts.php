<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard\Storage;

use Byfareska\Crud\Dashboard\DashboardLayouts;
use Byfareska\Crud\Dashboard\WidgetPlacement;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The layouts in a cache pool (cache.app by default): nothing to migrate, but gone with the cache - for an
 * application without a database, or to try the dashboards out.
 */
final readonly class CacheDashboardLayouts implements DashboardLayouts
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function find(?string $owner, string $board): ?array
    {
        $item = $this->cache->getItem($this->key($owner, $board));
        $data = $item->get();

        return $item->isHit() && is_array($data) ? WidgetPlacement::listFromArray($data) : null;
    }

    public function save(?string $owner, string $board, array $placements): void
    {
        $item = $this->cache->getItem($this->key($owner, $board));
        $item->set(array_map(static fn (WidgetPlacement $placement): array => $placement->toArray(), $placements));
        $this->cache->save($item);
    }

    public function delete(?string $owner, string $board): void
    {
        $this->cache->deleteItem($this->key($owner, $board));
    }

    /**
     * The one for everybody is under the owner "*", and a user whose id starts with "*" has one more in front ("*" is
     * "**"): a user "*" would otherwise read and change the layout for everybody as their own.
     */
    private function key(?string $owner, string $board): string
    {
        $owner = match (true) {
            $owner === null => '*',
            str_starts_with($owner, '*') => '*' . $owner,
            default => $owner,
        };

        return 'byfareska_crud.dashboard.' . hash('xxh128', $owner . "\0" . $board);
    }
}
