<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Doctrine;

use Byfareska\Crud\Files\Doctrine\DoctrineFileLibrary;
use Byfareska\Crud\Files\Doctrine\RecordFields;
use Byfareska\Crud\Files\FileLibrary;
use Byfareska\Crud\Files\FileRecordFactory;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\Testing\FileLibraryContractTestCase;
use Byfareska\Crud\Tests\Unit\Fixtures\Files\MediaFile;
use DateTimeImmutable;

/**
 * The library in a table of the application's, with names of its own (byfareska_crud.files.entity_fields) and made
 * by a factory of its own (files.record_factory).
 */
final class OwnEntityFileLibraryTest extends FileLibraryContractTestCase
{
    protected function library(): FileLibrary
    {
        $factory = new class implements FileRecordFactory {
            public function create(string $class, StoredFile $file): MediaFile
            {
                return new MediaFile(
                    (string) $file->reference,
                    $file->name,
                    $file->mimeType,
                    $file->createdAt ?? new DateTimeImmutable(),
                );
            }
        };

        return new DoctrineFileLibrary(
            FileEntities::registry($this, FileEntities::manager()),
            MediaFile::class,
            self::STORAGE,
            $factory,
            new FileRecordMappers(),
            RecordFields::fromConfig([
                'reference' => 'url',
                'name' => 'title',
                'mime_type' => 'kind',
                'created' => 'uploadedAt',
            ]),
        );
    }
}
