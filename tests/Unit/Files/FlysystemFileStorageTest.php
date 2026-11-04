<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\FileStorage;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use Byfareska\Crud\Files\Testing\FileStorageContractTestCase;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use stdClass;

final class FlysystemFileStorageTest extends FileStorageContractTestCase
{
    protected function storage(): FileStorage
    {
        return new FlysystemFileStorage(new Filesystem(new InMemoryFilesystemAdapter()));
    }

    public function testAFilesystemOfFlysystemIsMadeAStorage(): void
    {
        $storage = $this->storage();

        self::assertSame($storage, FlysystemFileStorage::adapt($storage));
        self::assertInstanceOf(
            FlysystemFileStorage::class,
            FlysystemFileStorage::adapt(new Filesystem(new InMemoryFilesystemAdapter())),
        );

        $this->expectException(InvalidArgumentException::class);
        FlysystemFileStorage::adapt(new stdClass());
    }

    public function testTheAddressesOfTheFilesystem(): void
    {
        $storage = new FlysystemFileStorage(new Filesystem(
            new InMemoryFilesystemAdapter(),
            ['public_url' => 'https://cdn.example.com/media'],
        ));

        self::assertSame('https://cdn.example.com/media/ab/x.jpg', $storage->publicUrl('ab/x.jpg'));
        self::assertNull($this->storage()->publicUrl('ab/x.jpg'), 'None configured.');
    }
}
