<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Command;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileStorageFailed;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\Image\ImageVariants;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the files of the sizes of images (byfareska_crud.files.images), never the images: all of them, those of some
 * sizes, or those of sizes no longer configured. Only counts them unless --force (-v lists them). A size of a storage
 * that makes them when asked for is made again then; one made on upload needs byfareska:crud:files:variants:generate.
 *
 * A rich text keeps the addresses of the sizes it shows: delete a size it may show only to make it again.
 */
#[AsCommand('byfareska:crud:files:variants:clear', 'Deletes the sizes of images made so far')]
final readonly class ClearImageVariantsCommand
{
    public function __construct(
        private FileStorages $storages,
        private ImageVariants $variants,
    ) {
    }

    /**
     * @param list<string> $storage
     * @param list<string> $variant
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only this storage (repeatable); default every one')]
        array $storage = [],
        #[Option('Only this size (repeatable); default every one, configured or not')]
        array $variant = [],
        #[Option('Only the sizes no longer in byfareska_crud.files.images.variants')]
        bool $unconfigured = false,
        #[Option('Delete them; without it they are only counted')]
        bool $force = false,
    ): int {
        $rows = [];
        $total = 0;
        foreach ($storage ?: $this->storages->names() as $name) {
            $found = $this->found($io, $name, $variant, $unconfigured);
            foreach ($found as $size => $references) {
                $rows[] = [$name, $size, count($references)];
                $total += count($references);
                foreach ($references as $reference) {
                    if ($io->isVerbose()) {
                        $io->writeln((string) $reference);
                    }
                    if ($force) {
                        $this->storages->storage($name)->delete($reference->path);
                    }
                }
            }
        }

        if ($rows !== []) {
            $io->table(['Storage', 'Size', 'Files'], $rows);
        }
        $io->success(sprintf(
            $force ? 'Deleted %d file(s) of sizes.' : '%d file(s) of sizes to delete: run again with --force.',
            $total,
        ));

        return 0;
    }

    /**
     * The files of sizes of a storage, by the name of their size: all of them first, so deleting does not change what
     * is listed.
     *
     * @param list<string> $only
     * @return array<string, list<FileReference>>
     */
    private function found(SymfonyStyle $io, string $storage, array $only, bool $unconfigured): array
    {
        $found = [];
        try {
            foreach ($this->storages->storage($storage)->list($this->variants->directory()) as $entry) {
                $reference = FileReference::tryParse($storage . '://' . $entry->path);
                $parsed = $reference instanceof FileReference ? $this->variants->parse($reference) : null;
                if (
                    $reference instanceof FileReference
                    && $parsed !== null
                    && ($only === [] || in_array($parsed[1], $only, true))
                    && (!$unconfigured || !$this->variants->has($parsed[1]))
                ) {
                    $found[$parsed[1]][] = $reference;
                }
            }
        } catch (FileStorageFailed $failure) {
            $io->warning(sprintf('The storage "%s" cannot be listed: %s', $storage, $failure->getMessage()));
        }
        ksort($found);

        return $found;
    }
}
