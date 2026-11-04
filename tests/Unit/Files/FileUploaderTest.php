<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Event\FileUploaded;
use Byfareska\Crud\Event\FileUploading;
use Byfareska\Crud\Files\FileLibraries;
use Byfareska\Crud\Files\FileQuery;
use Byfareska\Crud\Files\FileRefused;
use Byfareska\Crud\Files\FileStorages;
use Byfareska\Crud\Files\FileUploader;
use Byfareska\Crud\Files\Flysystem\FlysystemFileStorage;
use Byfareska\Crud\Files\ShardedPathGenerator;
use Byfareska\Crud\Files\StorageFileLibrary;
use Byfareska\Crud\Files\UploadProfile;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Uploads are checked against the profile by what the file is, written under a name of the server's and kept by the
 * library of the storage.
 */
final class FileUploaderTest extends TestCase
{
    /** A PNG of one pixel. */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVOR'
        . 'K5CYII=';

    private Filesystem $filesystem;

    private EventDispatcher $events;

    private FileUploader $uploader;

    private StorageFileLibrary $library;

    /** @var list<string> */
    private array $temporary = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $storage = new FlysystemFileStorage($this->filesystem);
        $this->library = new StorageFileLibrary($storage, 'public');
        $this->events = new EventDispatcher();
        $this->uploader = new FileUploader(
            new FileStorages(
                ['public' => [], 'legacy' => ['upload' => false]],
                new ServiceLocator([
                    'public' => static fn (): FlysystemFileStorage => $storage,
                    'legacy' => static fn (): FlysystemFileStorage => $storage,
                ]),
            ),
            new FileLibraries(new ServiceLocator(['public' => fn (): StorageFileLibrary => $this->library])),
            new ShardedPathGenerator(),
            $this->events,
            1024 * 1024,
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            @unlink($path);
        }
    }

    public function testAnImageIsWrittenUnderANameOfTheServer(): void
    {
        $uploaded = [];
        $this->events->addListener(FileUploaded::class, static function (FileUploaded $event) use (&$uploaded): void {
            $uploaded[] = $event->file;
        });

        $stored = $this->uploader->upload($this->png(), UploadProfile::images('public'), 'Kot.PNG');

        self::assertSame('public', $stored->reference->storage);
        self::assertMatchesRegularExpression('#^[0-9a-z]{2}/[0-9a-z]{2}/[0-9a-z]{32}\.png$#', $stored->reference->path);
        self::assertSame('Kot.PNG', $stored->name);
        self::assertSame('image/png', $stored->mimeType);
        self::assertSame([1, 1], [$stored->width, $stored->height]);
        self::assertTrue($this->filesystem->fileExists($stored->reference->path));
        self::assertSame([$stored], $uploaded);
        self::assertCount(1, $this->library->browse(new FileQuery('public'))->files);
    }

    public function testAScriptNamedAsAPhotoIsNoPhoto(): void
    {
        try {
            $script = $this->file('<?php system($_GET["c"]);');
            $this->uploader->upload($script, UploadProfile::images('public'), 'photo.jpg');
            self::fail('A script is no image.');
        } catch (FileRefused $refused) {
            self::assertSame('file.refused.kind', $refused->key);
        }
        self::assertSame([], $this->filesystem->listContents('', true)->toArray());
    }

    public function testAPhotoNamedAsAScriptGetsTheExtensionOfAPhoto(): void
    {
        $stored = $this->uploader->upload($this->png(), new UploadProfile('public'), 'shell.php');

        self::assertSame('png', $stored->reference->extension());
    }

    public function testSvgOnlyWhenTheFieldNamesIt(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->expectExceptionObject(FileRefused::kind('image/svg+xml'));
        $this->uploader->upload($this->file($svg), new UploadProfile('public', ['image/*']), 'logo.svg');
    }

    public function testNotBiggerThanTheProfileSays(): void
    {
        $this->expectExceptionObject(FileRefused::tooBig(10));

        $this->uploader->upload($this->file(str_repeat('a', 11)), new UploadProfile('public', [], 10), 'a.txt');
    }

    public function testNotIntoAStorageOnlyToPickFrom(): void
    {
        $this->expectExceptionObject(FileRefused::storage('legacy'));

        $this->uploader->upload($this->file('text'), new UploadProfile('legacy'), 'a.txt');
    }

    public function testAListenerMayRefuse(): void
    {
        $this->events->addListener(FileUploading::class, static function (FileUploading $event): void {
            $event->refuse('app.virus', ['name' => $event->name]);
        });

        try {
            $this->uploader->upload($this->file('text'), new UploadProfile('public'), 'a.txt');
            self::fail('Refused by the listener.');
        } catch (FileRefused $refused) {
            self::assertSame(['app.virus', ['name' => 'a.txt']], [$refused->key, $refused->parameters]);
        }
    }

    private function png(): File
    {
        return $this->file(base64_decode(self::PNG));
    }

    private function file(string $contents): File
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'crud');
        file_put_contents($path, $contents);
        $this->temporary[] = $path;

        return new File($path);
    }
}
