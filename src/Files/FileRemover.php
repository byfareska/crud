<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Event\FileRemoving;
use Byfareska\Crud\Files\Image\ImageVariants;
use InvalidArgumentException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Deletes a file: forgotten by its library, its bytes deleted from its storage - and the sizes of an image with them.
 * Not one records still use (by the
 * index of FileUsages) unless that is confirmed, nor one a listener of FileRemoving keeps, nor a file of another
 * site.
 */
final readonly class FileRemover
{
    public function __construct(
        private FileStorages $storages,
        private FileLibraries $libraries,
        private FileUsages $usages,
        private ?EventDispatcherInterface $events = null,
        private ?ImageVariants $variants = null,
    ) {
    }

    /**
     * @param bool $evenIfUsed delete a file records use as well (confirmed by the one deleting it)
     * @return bool whether it was deleted (false: a listener kept it)
     *
     * @throws FileInUse used by records, and not confirmed
     * @throws InvalidArgumentException a file of another site, or of no storage
     */
    public function remove(FileReference $reference, bool $evenIfUsed = false): bool
    {
        if ($reference->isExternal() || !$this->storages->has($reference->storage)) {
            throw new InvalidArgumentException(sprintf('"%s" is no file of a storage.', $reference));
        }
        $usages = $this->usages->of($reference);
        if ($usages !== [] && !$evenIfUsed) {
            throw new FileInUse($reference, $usages);
        }

        $removing = new FileRemoving($reference, $usages);
        $this->events?->dispatch($removing);
        if ($removing->kept) {
            return false;
        }

        $this->libraries->of($reference->storage)->remove($reference);
        $this->storages->storage($reference->storage)->delete($reference->path);
        $this->variants?->remove($reference);

        return true;
    }
}
