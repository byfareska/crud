<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Component;

use Byfareska\Crud\Files\BrowserFiles;
use Byfareska\Crud\Files\FileInUse;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FilePage;
use Byfareska\Crud\Files\FileQuery;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileRemover;
use Byfareska\Crud\Files\FileUsage;
use Byfareska\Crud\Files\LibraryCapability;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\UploadProfile;
use Byfareska\Crud\Panel\ScopedComponent;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The window of the library: the files uploaded before, of the storages a field picks from, searched and paged; some
 * of them chosen for the field (the file-library controller), a file no record uses deleted - one records use only
 * once that is confirmed. One for the pages of a panel (in its layout), opened for a field by the token it was drawn
 * with (BrowserFiles::libraryToken(): signed, the browser cannot widen it).
 */
#[AsLiveComponent('Crud:FileLibrary', template: 'components/Crud/FileLibrary.html.twig')]
final class FileLibrary implements ScopedComponent
{
    use DefaultActionTrait;

    public const int PER_PAGE = 24;

    /** The panel it is in. */
    #[LiveProp]
    public string $scope = '';

    /** What the field it was opened for may pick; "" = none yet. */
    #[LiveProp(writable: true)]
    public string $token = '';

    /** The storage in view; "" = the first one. */
    #[LiveProp(writable: true)]
    public string $storage = '';

    #[LiveProp(writable: true, onUpdated: 'searched')]
    public string $search = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    /** A file records use, whose deleting is asked again; null = none. */
    public ?FileInUse $inUse = null;

    /** The page of files of this render. */
    private ?FilePage $results = null;

    public function __construct(
        private readonly BrowserFiles $browser,
        private readonly FileLibraries $libraries,
        private readonly FileRemover $remover,
    ) {
    }

    public function panelScope(): ?string
    {
        return $this->scope === '' ? null : $this->scope;
    }

    /** Opened for a field: from the start. */
    #[LiveAction]
    public function open(#[LiveArg] string $token): void
    {
        $this->token = $token;
        $this->storage = '';
        $this->search = '';
        $this->page = 1;
    }

    /** Another storage, from its first page. */
    #[LiveAction]
    public function show(#[LiveArg] string $storage): void
    {
        $this->storage = $storage;
        $this->search = '';
        $this->page = 1;
    }

    #[LiveAction]
    public function goTo(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }

    /** What is typed is looked for from the first page. */
    public function searched(): void
    {
        $this->page = 1;
    }

    /**
     * The storages it shows: those of the field the one looking may use, whose library lists its files.
     *
     * @return list<string>
     */
    public function getStorages(): array
    {
        $profile = $this->profile();

        return $profile instanceof UploadProfile ? array_values(array_filter(
            $profile->storages(),
            fn (string $storage): bool => $this->browser->mayUse($storage)
                && $this->libraries->can($storage, LibraryCapability::Browse),
        )) : [];
    }

    public function getCurrentStorage(): ?string
    {
        $storages = $this->getStorages();

        return in_array($this->storage, $storages, true) ? $this->storage : ($storages[0] ?? null);
    }

    public function isMultiple(): bool
    {
        return $this->browser->libraryOf($this->token)[1] ?? false;
    }

    public function canSearch(): bool
    {
        $storage = $this->getCurrentStorage();

        return $storage !== null && $this->libraries->can($storage, LibraryCapability::Search);
    }

    public function canRemove(): bool
    {
        $storage = $this->getCurrentStorage();

        return $storage !== null && $this->libraries->can($storage, LibraryCapability::Remove);
    }

    public function getResults(): FilePage
    {
        return $this->results ??= $this->browse();
    }

    private function browse(): FilePage
    {
        $storage = $this->getCurrentStorage();
        $profile = $this->profile();
        if ($storage === null || !$profile instanceof UploadProfile) {
            return FilePage::empty();
        }

        return $this->libraries->of($storage)->browse(new FileQuery(
            $storage,
            $this->canSearch() ? $this->search : '',
            $profile->accept,
            max(1, $this->page),
            self::PER_PAGE,
        ));
    }

    /**
     * The files of the page, as the browser gets them (BrowserFiles::json()).
     *
     * @return list<array<string, mixed>>
     */
    public function getFiles(): array
    {
        return array_map($this->browser->json(...), $this->getResults()->files);
    }

    /**
     * Deletes a file of the storage in view; one records use only when confirmed.
     */
    #[LiveAction]
    public function remove(#[LiveArg] string $ref, #[LiveArg] bool $confirmed = false): void
    {
        $reference = FileReference::tryParse($ref);
        if (
            !$reference instanceof FileReference
            || $reference->storage !== $this->getCurrentStorage()
            || !$this->canRemove()
        ) {
            return;
        }
        $this->results = null;
        try {
            $this->remover->remove($reference, $confirmed);
            $this->inUse = null;
        } catch (FileInUse $inUse) {
            $this->inUse = $inUse;
        }
    }

    /**
     * Where the file asked about is used, as "resource #id, field".
     *
     * @return list<FileUsage>
     */
    public function getUsages(): array
    {
        return $this->inUse instanceof FileInUse ? $this->inUse->usages : [];
    }

    public function getInUseFile(): ?StoredFile
    {
        return $this->inUse instanceof FileInUse ? $this->libraries->describe($this->inUse->reference) : null;
    }

    private function profile(): ?UploadProfile
    {
        return $this->browser->libraryOf($this->token)[0] ?? null;
    }
}
