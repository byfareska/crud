<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Command;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileStorageFailed;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\Image\ImageVariantFailed;
use Byfareska\Crud\Files\Image\ImageVariants;
use Byfareska\Crud\Files\Image\VariantStrategy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Makes the sizes of the images already in the storages that make sizes (byfareska_crud.files.storages.<name>
 * .variants): those uploaded before the sizes were enabled, a size added to the configuration - or, with --overwrite,
 * a size whose box was changed, at the address it had.
 */
#[AsCommand('byfareska:crud:files:variants:generate', 'Makes the sizes of the images already uploaded')]
final readonly class GenerateImageVariantsCommand
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
        #[Option('Only this storage (repeatable); default every one that makes sizes')]
        array $storage = [],
        #[Option('Only this size (repeatable); default every one')]
        array $variant = [],
        #[Option('Make again the sizes that are there already')]
        bool $overwrite = false,
    ): int {
        foreach ($variant as $name) {
            if (!$this->variants->has($name)) {
                $io->error(sprintf(
                    'There is no size "%s" (byfareska_crud.files.images.variants has %s).',
                    $name,
                    implode(', ', array_keys($this->variants->all())),
                ));

                return 1;
            }
        }
        $names = $variant ?: array_keys($this->variants->all());

        $rows = [];
        foreach ($storage ?: $this->storages->names() as $name) {
            if ($this->variants->strategy($name) === VariantStrategy::None) {
                if ($storage !== []) {
                    $io->note(sprintf('The storage "%s" makes no sizes ("variants: none"): skipped.', $name));
                }
                continue;
            }
            $rows[] = [$name, ...$this->generate($io, $name, $names, $overwrite)];
        }

        $io->table(['Storage', 'Made', 'There already', 'Failed'], $rows);
        $failed = array_sum(array_column($rows, 3));
        if ($failed > 0) {
            $io->warning(sprintf('%d size(s) could not be made: see the log (-v lists them).', $failed));
        } else {
            $io->success('The sizes of the images are made.');
        }

        return 0;
    }

    /**
     * @param list<string> $names
     * @return array{int, int, int} made, there already, failed
     */
    private function generate(SymfonyStyle $io, string $storage, array $names, bool $overwrite): array
    {
        $made = 0;
        $there = 0;
        $failed = 0;
        try {
            // Listed first: the sizes made are written to the same storage.
            $entries = [...$this->storages->storage($storage)->list()];
        } catch (FileStorageFailed $failure) {
            $io->warning(sprintf('The storage "%s" cannot be listed: %s', $storage, $failure->getMessage()));

            return [0, 0, 0];
        }
        foreach ($entries as $entry) {
            $image = FileReference::tryParse($storage . '://' . $entry->path);
            if (!$image instanceof FileReference || $this->variants->isVariant($image)) {
                continue;
            }
            foreach ($names as $name) {
                if (!$this->variants->applies($image, $name)) {
                    continue;
                }
                if (!$overwrite && $this->variants->exists($image, $name)) {
                    ++$there;
                    continue;
                }
                try {
                    $this->variants->make($image, $name, true);
                    ++$made;
                } catch (ImageVariantFailed | FileStorageFailed $failure) {
                    ++$failed;
                    $this->variants->failed($image, $name, $failure);
                    if ($io->isVerbose()) {
                        $io->writeln(sprintf('<error>%s</error> %s: %s', $image, $name, $failure->getMessage()));
                    }
                }
            }
        }

        return [$made, $there, $failed];
    }
}
