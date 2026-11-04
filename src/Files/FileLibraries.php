<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;

/**
 * The library of each storage (byfareska_crud.files.storages.<name>.library, else files.library), by its name.
 */
final readonly class FileLibraries
{
    /**
     * @param ContainerInterface $libraries the FileLibrary of each storage, by its name
     */
    public function __construct(
        private ContainerInterface $libraries,
    ) {
    }

    /**
     * @throws InvalidArgumentException no such storage
     */
    public function of(string $storage): FileLibrary
    {
        if (!$this->libraries->has($storage)) {
            throw new InvalidArgumentException(sprintf('There is no storage "%s".', $storage));
        }
        $library = $this->libraries->get($storage);
        if (!$library instanceof FileLibrary) {
            throw new InvalidArgumentException(sprintf('The library of the storage "%s" is no FileLibrary.', $storage));
        }

        return $library;
    }

    public function can(string $storage, LibraryCapability $capability): bool
    {
        return in_array($capability, $this->of($storage)->capabilities(), true);
    }

    /**
     * What is known of a file: by its library, else its name only. A file of another site is its address.
     */
    public function describe(FileReference $reference): StoredFile
    {
        if ($reference->isExternal() || !$this->libraries->has($reference->storage)) {
            return StoredFile::of($reference);
        }

        return $this->of($reference->storage)->find($reference) ?? StoredFile::of($reference);
    }
}
