<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Testing;

use Byfareska\Crud\Files\FileRecordMapper;
use Byfareska\Crud\Files\FileReference;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * What every FileRecordMapper does, for a mapper of the application (an entity of another bundle) to be tested
 * against: a test of it extends this one and gives the mapper and an object of a file it knows, findable.
 */
abstract class FileRecordMapperContractTestCase extends TestCase
{
    abstract protected function mapper(): FileRecordMapper;

    /** An object of a file the mapper supports, which find() finds (kept where the mapper looks). */
    abstract protected function record(): object;

    public function testItKnowsItsObjectsOnly(): void
    {
        self::assertTrue($this->mapper()->supports($this->record()));
        self::assertFalse($this->mapper()->supports(new stdClass()));
    }

    public function testTheObjectOfAFileIsFoundByItsReference(): void
    {
        $record = $this->record();
        $mapper = $this->mapper();
        $file = $mapper->toStoredFile($record);

        $found = $mapper->find($record::class, $file->reference);

        self::assertNotNull($found);
        self::assertTrue($mapper->toStoredFile($found)->reference->equals($file->reference));
        self::assertNull($mapper->find($record::class, new FileReference(
            $file->reference->isExternal() ? 'public' : $file->reference->storage,
            'no/such/file.bin',
        )));
        self::assertNull($mapper->find(stdClass::class, $file->reference), 'Not of a class it does not know.');
    }
}
