<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Doctrine;

use Byfareska\Crud\Event\FileRemoving;
use Byfareska\Crud\Files\FileInUse;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileRemover;
use Byfareska\Crud\Files\FileUsage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A file is deleted from its storage and forgotten by its library - not one records use unless confirmed.
 */
final class FileRemoverTest extends TestCase
{
    private FilesOfAStorage $files;

    private EventDispatcher $events;

    private FileRemover $remover;

    protected function setUp(): void
    {
        $this->files = new FilesOfAStorage($this);
        $this->events = new EventDispatcher();
        $this->remover = new FileRemover(
            $this->files->storages,
            $this->files->libraries,
            $this->files->usages,
            $this->events,
        );
    }

    public function testAnUnusedFileIsDeleted(): void
    {
        $file = $this->files->upload('ab/a.txt');

        self::assertTrue($this->remover->remove($file));
        self::assertFalse($this->files->has($file));
        self::assertFalse($this->files->filesystem->fileExists('ab/a.txt'));
    }

    public function testAFileInUseIsDeletedOnlyWhenThatIsConfirmed(): void
    {
        $file = $this->files->upload('ab/a.txt');
        $this->files->usages->replace('product', '7', 'gallery', [$file]);

        try {
            $this->remover->remove($file);
            self::fail('A file in use was deleted.');
        } catch (FileInUse $inUse) {
            self::assertEquals([new FileUsage('product', '7', 'gallery')], $inUse->usages);
        }
        self::assertTrue($this->files->has($file));

        self::assertTrue($this->remover->remove($file, evenIfUsed: true));
        self::assertFalse($this->files->has($file));
    }

    public function testAListenerKeepsAFile(): void
    {
        $file = $this->files->upload('ab/a.txt');
        $this->events->addListener(FileRemoving::class, static fn (FileRemoving $removing) => $removing->keep());

        self::assertFalse($this->remover->remove($file));
        self::assertTrue($this->files->has($file));
    }

    public function testNotAFileOfAnotherSite(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->remover->remove(FileReference::parse('https://cdn.example.com/a.png'));
    }

    public function testNotAFileOfNoStorage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->remover->remove(FileReference::parse('avatars://a.png'));
    }
}
