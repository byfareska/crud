<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Every FileRecordMapper: the first that supports an object says what file it is.
 */
final readonly class FileRecordMappers
{
    /**
     * @param iterable<FileRecordMapper> $mappers
     */
    public function __construct(
        #[AutowireIterator(FileRecordMapper::TAG)]
        private iterable $mappers = [],
    ) {
    }

    /** What file an object is; null = none any mapper knows (nor a FileRecord). */
    public function toStoredFile(object $record): ?StoredFile
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->supports($record)) {
                return $mapper->toStoredFile($record);
            }
        }

        return $record instanceof FileRecord ? $record->storedFile() : null;
    }

    /**
     * @param class-string $class
     */
    public function find(string $class, FileReference $reference): ?object
    {
        foreach ($this->mappers as $mapper) {
            $found = $mapper->find($class, $reference);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
