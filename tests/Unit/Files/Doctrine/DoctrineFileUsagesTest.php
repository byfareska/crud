<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Files\Doctrine;

use Byfareska\Crud\Files\Doctrine\DoctrineFileUsages;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileUsage;
use PHPUnit\Framework\TestCase;

/**
 * The index of where files are used, in the table crud_file_usage.
 */
final class DoctrineFileUsagesTest extends TestCase
{
    public function testAFieldUsesTheFilesItHoldsNow(): void
    {
        $usages = new DoctrineFileUsages(FileEntities::registry($this, FileEntities::manager()));
        $a = FileReference::parse('public://a.png');
        $b = FileReference::parse('public://b.png');
        $c = FileReference::parse('public://c.png');

        $external = FileReference::parse('https://cdn.example.com/x.png');
        $usages->replace('product', '7', 'gallery', [$a, $b, $a, $external]);
        $usages->replace('product', '8', 'gallery', [$b]);
        $usages->replace('product', '7', 'gallery', [$b, $c]);

        self::assertSame([], $usages->of($a), 'No longer in the gallery.');
        self::assertEquals(
            [new FileUsage('product', '7', 'gallery'), new FileUsage('product', '8', 'gallery')],
            $usages->of($b),
        );
        self::assertEquals([$b, $c], $usages->used([$a, $b, $c]));
    }

    public function testADeletedRecordUsesNothing(): void
    {
        $usages = new DoctrineFileUsages(FileEntities::registry($this, FileEntities::manager()));
        $a = FileReference::parse('public://a.png');
        $usages->replace('product', '7', 'gallery', [$a]);
        $usages->replace('product', '7', 'manual', [$a]);

        $usages->forget('product', '7');

        self::assertSame([], $usages->used([$a]));
    }
}
