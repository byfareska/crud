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

    private function key(?string $owner, string $board): string
    {
        return 'byfareska_crud.dashboard.' . hash('xxh128', ($owner ?? '*') . "\0" . $board);
    }
}
