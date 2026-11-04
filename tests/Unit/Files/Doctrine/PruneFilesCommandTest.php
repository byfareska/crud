<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Doctrine;

use Byfareska\Crud\Files\Command\PruneFilesCommand;
use Byfareska\Crud\Files\FileRemover;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\Crud\Files\NoFileUsages;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Files no record uses are deleted once they are older than the grace period - those of forms never saved.
 */
final class PruneFilesCommandTest extends TestCase
{
    private FilesOfAStorage $files;

    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->files = new FilesOfAStorage($this);
        $this->output = new BufferedOutput();
    }

    public function testOnlyOldFilesNoRecordUses(): void
    {
        $abandoned = $this->files->upload('ab/abandoned.txt', '-2 days');
        $used = $this->files->upload('ab/used.txt', '-2 days');
        $fresh = $this->files->upload('ab/fresh.txt', '-1 hour');
        $this->files->usages->replace('product', '7', 'manual', [$used]);

        $this->prune(force: true);

        self::assertFalse($this->files->has($abandoned));
        self::assertTrue($this->files->has($used));
        self::assertTrue($this->files->has($fresh), 'Its form may not be saved yet.');
        self::assertStringContainsString('Deleted 1 file(s).', $this->output->fetch());
    }

    public function testWithoutForceTheyAreOnlyListed(): void
    {
        $abandoned = $this->files->upload('ab/abandoned.txt', '-2 days');

        $this->prune();

        self::assertTrue($this->files->has($abandoned));
        $output = $this->output->fetch();
        self::assertStringContainsString('public://ab/abandoned.txt', $output);
        self::assertStringContainsString('1 file(s) to delete', $output);
    }

    public function testTheGracePeriodIsChosen(): void
    {
        $file = $this->files->upload('ab/a.txt', '-3 hours');

        $this->prune(olderThan: 'PT1H', force: true);

        self::assertFalse($this->files->has($file));
    }

    public function testNothingWithoutAnIndexOfUsages(): void
    {
        $file = $this->files->upload('ab/a.txt', '-2 days');

        $this->prune(force: true, usages: new NoFileUsages());

        self::assertTrue($this->files->has($file));
        self::assertStringContainsString('nothing is pruned', $this->output->fetch());
    }

    public function testAStorageWhoseLibraryKnowsNoFilesIsSkipped(): void
    {
        $this->prune(['archive'], force: true);

        self::assertStringContainsString('"archive" cannot list or delete its files', $this->output->fetch());
    }

    /**
     * @param list<string> $storage
     */
    private function prune(
        array $storage = [],
        ?string $olderThan = null,
        bool $force = false,
        ?FileUsages $usages = null,
    ): void {
        $usages ??= $this->files->usages;
        $command = new PruneFilesCommand(
            $this->files->storages,
            $this->files->libraries,
            $usages,
            new FileRemover($this->files->storages, $this->files->libraries, $usages),
        );

        self::assertSame(0, $command(
            new SymfonyStyle(new ArrayInput([]), $this->output),
            $storage,
            $olderThan,
            $force,
        ));
    }
}
