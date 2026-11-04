<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Doctrine;

use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileRecord;
use Byfareska\Crud\Files\FileRecordMapper;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StoredFile;

/**
 * The entities of Doctrine's library (byfareska_crud.files.entity): what file each is (FileRecord), and the entity of
 * a file, found by the library of its storage.
 */
final readonly class DoctrineFileRecordMapper implements FileRecordMapper
{
    /**
     * @param class-string $class
     */
    public function __construct(
        private string $class,
        private FileLibraries $libraries,
    ) {
    }

    public function supports(object $record): bool
    {
        return $record instanceof $this->class && $record instanceof FileRecord;
    }

    public function toStoredFile(object $record): StoredFile
    {
        assert($record instanceof FileRecord);

        return $record->storedFile();
    }

    public function find(string $class, FileReference $reference): ?object
    {
        if (!is_a($this->class, $class, true) || $reference->isExternal()) {
            return null;
        }
        $library = $this->libraries->of($reference->storage);

        return $library instanceof DoctrineFileLibrary ? $library->record($reference) : null;
    }
}
