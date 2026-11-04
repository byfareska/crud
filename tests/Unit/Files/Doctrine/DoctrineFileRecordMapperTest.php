<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Doctrine;

use Byfareska\Crud\Files\ConstructorFileRecordFactory;
use Byfareska\Crud\Files\Doctrine\DoctrineFileLibrary;
use Byfareska\Crud\Files\Doctrine\DoctrineFileRecordMapper;
use Byfareska\Crud\Files\Doctrine\Record\CrudFileRecord;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileRecordMapper;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\Testing\FileRecordMapperContractTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DoctrineFileRecordMapperTest extends FileRecordMapperContractTestCase
{
    private DoctrineFileLibrary $library;

    protected function setUp(): void
    {
        $this->library = new DoctrineFileLibrary(
            FileEntities::registry($this, FileEntities::manager()),
            CrudFileRecord::class,
            'public',
            new ConstructorFileRecordFactory(),
            new FileRecordMappers(),
        );
    }

    protected function mapper(): FileRecordMapper
    {
        return new DoctrineFileRecordMapper(
            CrudFileRecord::class,
            new FileLibraries(new ServiceLocator(['public' => fn (): DoctrineFileLibrary => $this->library])),
        );
    }

    protected function record(): object
    {
        $reference = FileReference::parse('public://ab/cd/photo.png');
        $this->library->register(new StoredFile($reference, 'photo.png', 'image/png'));

        return $this->library->record($reference) ?? self::fail('The file was not kept.');
    }
}
