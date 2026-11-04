<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files;

use Byfareska\Crud\Files\ShardedPathGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ShardedPathGeneratorTest extends TestCase
{
    public function testARandomNameInDirectoriesOfItsFirstCharacters(): void
    {
        $path = new ShardedPathGenerator()->generate('public', 'jpg', 'Kot.jpg');

        self::assertMatchesRegularExpression('#^([0-9a-z]{2})/([0-9a-z]{2})/\1\2[0-9a-z]{28}\.jpg$#', $path);
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

    public function testTheLevelsFitInTheName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShardedPathGenerator(levels: 5, width: 2, length: 8);
    }
}
