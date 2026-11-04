<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Psr\Cache\CacheItemInterface;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * The library of a storage without a table: its files as the storage lists them (byfareska_crud.files.library:
 * storage). Nothing to set up, but it knows only what the storage does - no name a file was uploaded under (found by
 * its path: ShardedPathGenerator's keepName puts the name there), no size of an image - and listing a storage on FTP
 * asks the server for every directory: the list is kept in the cache for a while ($ttl seconds).
 */
final readonly class StorageFileLibrary implements FileLibrary
{
    public function __construct(
        private FileStorage $storage,
        private string $name,
        private ?CacheItemPoolInterface $cache = null,
        private int $ttl = 60,
    ) {
    }

    public function register(StoredFile $file): StoredFile
    {
        $this->forgetList();

        return $file;
    }

    public function find(FileReference $reference): ?StoredFile
    {
        $entry = $this->storage->entry($reference->path);

        return $entry instanceof StorageEntry ? $this->file($entry) : null;
    }

    public function browse(FileQuery $query): FilePage
    {
        $words = array_filter(
            explode(' ', strtolower(trim($query->search))),
            static fn (string $word): bool => $word !== '',
        );
        $found = [];
        foreach ($this->entries() as $entry) {
            $file = $this->file($entry);
            $path = strtolower($entry->path);
            if (
                $query->wants($file)
                && array_all($words, static fn (string $word): bool => str_contains($path, $word))
            ) {
                $found[] = $file;
            }
        }

        $page = array_slice($found, $query->offset(), $query->perPage);

        return new FilePage($page, $query->page, count($found), $query->offset() + count($page) < count($found));
    }

    public function remove(FileReference $reference): void
    {
        $this->forgetList();
    }

    public function capabilities(): array
    {
        return [LibraryCapability::Browse, LibraryCapability::Search, LibraryCapability::Remove];
    }

    /**
     * Every file of the storage, the newest first.
     *
     * @return list<StorageEntry>
     */
    private function entries(): array
    {
        $item = $this->cache?->getItem($this->cacheKey());
        if ($item instanceof CacheItemInterface && $item->isHit() && is_array($cached = $item->get())) {
            return array_values(array_filter(
                $cached,
                static fn (mixed $entry): bool => $entry instanceof StorageEntry,
            ));
        }

        $entries = [...$this->storage->list()];
        usort(
            $entries,
            static fn (StorageEntry $a, StorageEntry $b): int => ($b->lastModified?->getTimestamp() ?? 0)
                <=> ($a->lastModified?->getTimestamp() ?? 0),
        );
        if ($item instanceof CacheItemInterface && $this->cache instanceof CacheItemPoolInterface) {
            $this->cache->save($item->set($entries)->expiresAfter($this->ttl));
        }

        return $entries;
    }

    private function file(StorageEntry $entry): StoredFile
    {
        $reference = new FileReference($this->name, $entry->path);
        $mimeType = $entry->mimeType;
        if ($mimeType === null && $reference->extension() !== '' && class_exists(MimeTypes::class)) {
            $mimeType = MimeTypes::getDefault()->getMimeTypes($reference->extension())[0] ?? null;
        }

        return new StoredFile(
            $reference,
            $reference->basename(),
            $mimeType,
            $entry->size,
            createdAt: $entry->lastModified ?? new DateTimeImmutable('@0'),
        );
    }

    private function forgetList(): void
    {
        $this->cache?->deleteItem($this->cacheKey());
    }

    private function cacheKey(): string
    {
        return 'byfareska_crud.files.' . $this->name;
    }
}
