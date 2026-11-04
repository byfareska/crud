<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Doctrine;

use Byfareska\Crud\Files\ConstructorFileRecordFactory;
use Byfareska\Crud\Files\Doctrine\DoctrineFileLibrary;
use Byfareska\Crud\Files\Doctrine\Record\CrudFileRecord;
use Byfareska\Crud\Files\FileLibrary;
use Byfareska\Crud\Files\FileQuery;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\Testing\FileLibraryContractTestCase;

/**
 * The library in the table of the bundle's entity (crud_file).
 */
final class DoctrineFileLibraryTest extends FileLibraryContractTestCase
{
    protected function library(): FileLibrary
    {
        return new DoctrineFileLibrary(
            FileEntities::registry($this, FileEntities::manager()),
            CrudFileRecord::class,
            self::STORAGE,
            new ConstructorFileRecordFactory(),
            new FileRecordMappers(),
        );
    }

    public function testWhatItKeepsOfAFile(): void
    {
        $library = $this->library();
        $library->register(new StoredFile(
            FileReference::parse('public://ab/cd/x.png'),
            'Kot na płocie.png',
            'image/png',
            1234,
            800,
            600,
        ));

        $found = $library->find(FileReference::parse('public://ab/cd/x.png'));

        self::assertNotNull($found);
        self::assertSame(['Kot na płocie.png', 'image/png', 1234, 800, 600], [
            $found->name,
            $found->mimeType,
            $found->size,
            $found->width,
            $found->height,
        ]);
        self::assertSame([], $library->browse(new FileQuery('private'))->files, 'Only the files of its storage.');
        self::assertCount(1, $library->browse(new FileQuery(self::STORAGE, 'płocie kot'))->files);
    }
}
