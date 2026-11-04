<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Flysystem;

use Byfareska\Crud\Files\FileStorage;
use Byfareska\Crud\Files\FileStorageFailed;
use Byfareska\Crud\Files\StorageEntry;
use DateTimeImmutable;
use InvalidArgumentException;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToRetrieveMetadata;
use Throwable;

/**
 * A storage of Flysystem: a FilesystemOperator (a service of league/flysystem-bundle, "default.storage") as the
 * "service" of byfareska_crud.files.storages.<name> becomes one of these by itself (adapt()).
 */
final readonly class FlysystemFileStorage implements FileStorage
{
    public function __construct(
        private FilesystemOperator $filesystem,
    ) {
    }

    /**
     * The FileStorage of the service a storage names: itself, or a FilesystemOperator in one of these.
     */
    public static function adapt(object $service): FileStorage
    {
        return match (true) {
            $service instanceof FileStorage => $service,
            $service instanceof FilesystemOperator => new self($service),
            default => throw new InvalidArgumentException(sprintf(
                'The service of a storage is a FileStorage or a FilesystemOperator of Flysystem, not %s.',
                $service::class,
            )),
        };
    }

    public function write(string $path, mixed $contents, ?string $mimeType = null): void
    {
        try {
            $this->filesystem->writeStream($path, $contents, $mimeType === null ? [] : ['mimetype' => $mimeType]);
        } catch (FilesystemException $exception) {
            throw new FileStorageFailed($exception->getMessage(), 0, $exception);
        }
    }

    public function readStream(string $path): mixed
    {
        try {
            return $this->filesystem->readStream($path);
        } catch (FilesystemException $exception) {
            throw new FileStorageFailed($exception->getMessage(), 0, $exception);
        }
    }

    public function exists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path);
        } catch (FilesystemException $exception) {
            throw new FileStorageFailed($exception->getMessage(), 0, $exception);
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->filesystem->delete($path);
        } catch (FilesystemException $exception) {
            throw new FileStorageFailed($exception->getMessage(), 0, $exception);
        }
    }

    public function list(string $directory = ''): iterable
    {
        try {
            foreach ($this->filesystem->listContents($directory, true) as $item) {
                if ($item instanceof StorageAttributes && $item->isFile()) {
                    yield $this->entryOf($item);
                }
            }
        } catch (FilesystemException $exception) {
            throw new FileStorageFailed($exception->getMessage(), 0, $exception);
        }
    }

    public function entry(string $path): ?StorageEntry
    {
        try {
            if (!$this->filesystem->fileExists($path)) {
                return null;
            }
            $size = $this->filesystem->fileSize($path);
            $modified = $this->filesystem->lastModified($path);
        } catch (FilesystemException $exception) {
            throw new FileStorageFailed($exception->getMessage(), 0, $exception);
        }
        try {
            $mimeType = $this->filesystem->mimeType($path);
        } catch (UnableToRetrieveMetadata) {
            $mimeType = null;
        }

        return new StorageEntry($path, $size, $mimeType, new DateTimeImmutable('@' . $modified));
    }

    public function publicUrl(string $path): ?string
    {
        if (!method_exists($this->filesystem, 'publicUrl')) {
            return null;
        }
        try {
            $url = $this->filesystem->publicUrl($path);
        } catch (Throwable) {
            // No public_url configured, or an adapter that has none.
            return null;
        }

        return is_string($url) ? $url : null;
    }

    public function temporaryUrl(string $path, DateTimeImmutable $expiresAt): ?string
    {
        if (!method_exists($this->filesystem, 'temporaryUrl')) {
            return null;
        }
        try {
            $url = $this->filesystem->temporaryUrl($path, $expiresAt);
        } catch (Throwable) {
            return null;
        }

        return is_string($url) ? $url : null;
    }

    private function entryOf(StorageAttributes $item): StorageEntry
    {
        $modified = $item->lastModified();

        return new StorageEntry(
            $item->path(),
            $item instanceof FileAttributes ? $item->fileSize() : null,
            $item instanceof FileAttributes ? $item->mimeType() : null,
            $modified === null ? null : new DateTimeImmutable('@' . $modified),
        );
    }
}
