<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Command;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileQuery;
use Byfareska\Crud\Files\FileRemover;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\Crud\Files\LibraryCapability;
use Byfareska\Crud\Files\StoredFile;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deletes the files no record uses (by the index of FileUsages) that were uploaded longer ago than
 * byfareska_crud.files.prune_after - those of forms never saved, those a record no longer has. Only lists them unless
 * --force. Nothing without an index of usages (FileUsages::tracks()), nor in a storage whose library cannot list its
 * files or delete them.
 *
 * Run byfareska:crud:files:reindex first when records are changed outside of the panel.
 */
#[AsCommand('byfareska:crud:files:prune', 'Deletes the uploaded files no record uses')]
final readonly class PruneFilesCommand
{
    private const int PAGE = 200;

    public function __construct(
        private FileStorages $storages,
        private FileLibraries $libraries,
        private FileUsages $usages,
        private FileRemover $remover,
        #[Autowire(param: 'byfareska_crud.files.prune_after')]
        private string $pruneAfter = 'PT24H',
    ) {
    }

    /**
     * @param list<string> $storage
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option('Only this storage (repeatable); default every one')]
        array $storage = [],
        #[Option('Only files older than this ISO 8601 duration (PT24H, P7D); default files.prune_after')]
        ?string $olderThan = null,
        #[Option('Delete them; without it they are only listed')]
        bool $force = false,
    ): int {
        if (!$this->usages->tracks()) {
            $io->warning('No index of where files are used (byfareska_crud.files.usages: none): nothing is pruned.');

            return 0;
        }
        $before = new DateTimeImmutable()->sub(new DateInterval($olderThan ?? $this->pruneAfter));

        $total = 0;
        foreach ($storage ?: $this->storages->names() as $name) {
            if (
                !$this->libraries->can($name, LibraryCapability::Browse)
                || !$this->libraries->can($name, LibraryCapability::Remove)
            ) {
                $io->note(sprintf('The library of "%s" cannot list or delete its files: skipped.', $name));
                continue;
            }
            $unused = $this->unused($name, $before);
            foreach ($unused as $file) {
                $io->writeln(sprintf('%s  <comment>%s</comment>', $file->reference, $file->name));
                if ($force) {
                    $this->remover->remove($file->reference);
                }
            }
            $total += count($unused);
        }

        $io->success(sprintf($force ? 'Deleted %d file(s).' : '%d file(s) to delete: run again with --force.', $total));

        return 0;
    }

    /**
     * The files of a storage, older than the moment, no record uses: all of them first, so deleting does not move the
     * pages.
     *
     * @return list<StoredFile>
     */
    private function unused(string $storage, DateTimeImmutable $before): array
    {
        $library = $this->libraries->of($storage);
        $unused = [];
        $page = 1;
        do {
            $found = $library->browse(new FileQuery($storage, page: $page++, perPage: self::PAGE));
            $old = array_values(array_filter(
                $found->files,
                static fn (StoredFile $file): bool => $file->createdAt instanceof DateTimeImmutable
                    && $file->createdAt < $before,
            ));
            $used = array_map(strval(...), $this->usages->used(array_map(
                static fn (StoredFile $file): FileReference => $file->reference,
                $old,
            )));
            foreach ($old as $file) {
                if (!in_array((string) $file->reference, $used, true)) {
                    $unused[] = $file;
                }
            }
        } while ($found->more);

        return $unused;
    }
}
