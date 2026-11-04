<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Doctrine;

use Byfareska\Crud\Files\ConstructorFileRecordFactory;
use Byfareska\Crud\Files\Doctrine\DoctrineFileLibrary;
use Byfareska\Crud\Files\Doctrine\DoctrineFileUsages;
use Byfareska\Crud\Files\Doctrine\Record\CrudFileRecord;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use Byfareska\Crud\Files\FileRecordMappers;
use Byfareska\Crud\Files\NoFileLibrary;
use Byfareska\Crud\Files\StoredFile;
use DateTimeImmutable;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The storage "public" in memory with its library and the index of usages in SQLite, and the storage "archive" whose
 * library knows no files.
 */
final readonly class FilesOfAStorage
{
    public Filesystem $filesystem;

    public FileStorages $storages;

    public FileLibraries $libraries;

    public DoctrineFileLibrary $library;

    public DoctrineFileUsages $usages;

    public function __construct(TestCase $test)
    {
        $registry = FileEntities::registry($test, FileEntities::manager());
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $storage = new FlysystemFileStorage($this->filesystem);
        $this->storages = new FileStorages(['public' => [], 'archive' => []], new ServiceLocator([
            'public' => static fn (): FlysystemFileStorage => $storage,
            'archive' => static fn (): FlysystemFileStorage => $storage,
        ]));
        $this->library = new DoctrineFileLibrary(
            $registry,
            CrudFileRecord::class,
            'public',
            new ConstructorFileRecordFactory(),
            new FileRecordMappers(),
        );
        $this->libraries = new FileLibraries(new ServiceLocator([
            'public' => fn (): DoctrineFileLibrary => $this->library,
            'archive' => static fn (): NoFileLibrary => new NoFileLibrary(),
        ]));
        $this->usages = new DoctrineFileUsages($registry);
    }

    /**
     * A file written to the storage and kept by the library, uploaded at the moment.
     */
    public function upload(string $path, string $uploaded = 'now'): FileReference
    {
        $reference = new FileReference('public', $path);
        $this->filesystem->write($path, 'data');
        $this->library->register(new StoredFile(
            $reference,
            basename($path),
            'text/plain',
            4,
            createdAt: new DateTimeImmutable($uploaded),
        ));

        return $reference;
    }

    public function has(FileReference $reference): bool
    {
        return $this->filesystem->fileExists($reference->path)
            && $this->library->find($reference) instanceof StoredFile;
    }
}
