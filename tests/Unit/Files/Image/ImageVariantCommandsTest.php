<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Image;

use Byfareska\Crud\Files\Command\ClearImageVariantsCommand;
use Byfareska\Crud\Files\Command\GenerateImageVariantsCommand;
use Byfareska\Crud\Files\FileReference;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The sizes of the images already uploaded are made, and deleted, by commands - never the images.
 */
final class ImageVariantCommandsTest extends TestCase
{
    private ImagesKit $kit;

    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->kit = new ImagesKit();
        $this->output = new BufferedOutput();
    }

    public function testTheSizesOfTheImagesThereAreMade(): void
    {
        $image = $this->kit->image('public://ab/a.png');
        $this->kit->filesystem->write('ab/notes.txt', 'not an image');

        $this->generate();

        foreach (['thumbnail', 'preview', 'large'] as $name) {
            self::assertTrue($this->kit->variants->exists($image, $name), $name);
        }
        self::assertCount(3, $this->kit->resizer->made);

        // Again: those there are kept, unless made again.
        $this->generate();
        self::assertCount(3, $this->kit->resizer->made);
        $this->generate(variant: ['large'], overwrite: true);
        self::assertCount(4, $this->kit->resizer->made);
    }

    public function testOnlyTheStoragesThatMakeSizes(): void
    {
        $image = $this->kit->image('plain://ab/a.png');

        $this->generate();

        self::assertFalse($this->kit->variants->exists($image, 'thumbnail'));
        self::assertSame([], $this->kit->resizer->made);
    }

    public function testASizeThereIsNot(): void
    {
        self::assertSame(1, $this->generate(variant: ['huge']));
        self::assertStringContainsString('There is no size "huge"', $this->output->fetch());
    }

    public function testWithoutForceTheSizesAreOnlyCounted(): void
    {
        $image = $this->kit->image('public://ab/a.png');
        $this->kit->variants->makeAll($image);

        $this->clear();

        self::assertTrue($this->kit->variants->exists($image, 'thumbnail'));
        self::assertStringContainsString('3 file(s) of sizes to delete', $this->output->fetch());
    }

    public function testTheSizesAreDeletedNotTheImages(): void
    {
        $image = $this->kit->image('public://ab/a.png');
        $this->kit->variants->makeAll($image);

        $this->clear(variant: ['thumbnail'], force: true);

        self::assertFalse($this->kit->variants->exists($image, 'thumbnail'));
        self::assertTrue($this->kit->variants->exists($image, 'preview'));
        self::assertTrue($this->kit->has($image));
        self::assertStringContainsString('Deleted 1 file(s) of sizes.', $this->output->fetch());
    }

    public function testOnlyTheSizesNoLongerConfigured(): void
    {
        $image = $this->kit->image('public://ab/a.png');
        $this->kit->variants->makeAll($image);
        $old = FileReference::parse('public://_variants/old/ab/a.png');
        $this->kit->filesystem->write($old->path, 'an old size');

        $this->clear(unconfigured: true, force: true);

        self::assertFalse($this->kit->has($old));
        self::assertTrue($this->kit->variants->exists($image, 'thumbnail'));
    }

    /**
     * @param list<string> $variant
     */
    private function generate(array $variant = [], bool $overwrite = false): int
    {
        return new GenerateImageVariantsCommand($this->kit->storages, $this->kit->variants)(
            new SymfonyStyle(new ArrayInput([]), $this->output),
            variant: $variant,
            overwrite: $overwrite,
        );
    }

    /**
     * @param list<string> $variant
     */
    private function clear(array $variant = [], bool $unconfigured = false, bool $force = false): int
    {
        return new ClearImageVariantsCommand($this->kit->storages, $this->kit->variants)(
            new SymfonyStyle(new ArrayInput([]), $this->output),
            variant: $variant,
            unconfigured: $unconfigured,
            force: $force,
        );
    }
}
