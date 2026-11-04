<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\ShardedPathGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ShardedPathGeneratorTest extends TestCase
{
    public function testARandomNameInRandomDirectories(): void
    {
        $path = new ShardedPathGenerator()->generate('public', 'jpg', 'Kot.jpg');

        self::assertMatchesRegularExpression('#^[0-9a-z]{2}/[0-9a-z]{2}/[0-9a-z]{32}\.jpg$#', $path);
    }

    public function testTheDirectoriesAreNotTheBeginningOfTheName(): void
    {
        $generator = new ShardedPathGenerator();
        $repeated = 0;
        for ($i = 0; $i < 20; ++$i) {
            [$first, $second, $name] = explode('/', $generator->generate('public', 'jpg', 'Kot.jpg'));
            $repeated += (int) str_starts_with($name, $first . $second);
        }

        // By chance one in 36^4 (1.7 million) of them.
        self::assertLessThan(2, $repeated);
    }

    public function testNoDirectoriesForAStorageOfPrefixes(): void
    {
        $path = new ShardedPathGenerator(levels: 0, length: 16)->generate('s3', '', 'README');

        self::assertMatchesRegularExpression('#^[0-9a-z]{16}$#', $path);
    }

    public function testTheNameItCameWithMadePlain(): void
    {
        $path = new ShardedPathGenerator(keepName: true)->generate('public', 'pdf', 'Faktura 2026/10 (kopia).PDF');

        self::assertMatchesRegularExpression(
            '#^[0-9a-z]{2}/[0-9a-z]{2}/[0-9a-z]{32}-faktura-2026-10-kopia\.pdf$#',
            $path,
        );
    }

    public function testTwoPathsDiffer(): void
    {
        $generator = new ShardedPathGenerator();

        self::assertNotSame($generator->generate('public', 'jpg', 'a'), $generator->generate('public', 'jpg', 'a'));
    }

    public function testANameOfEightCharactersAtLeast(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShardedPathGenerator(length: 7);
    }
}
