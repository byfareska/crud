<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\UseCase;

use Byfareska\Crud\UseCase\Sort;
use PHPUnit\Framework\TestCase;
use SortDirection;

final class SortTest extends TestCase
{
    public function testParsesTheDirectionFromTheMinus(): void
    {
        [$name, $created] = Sort::parseAll(['name', '-createdAt']);

        self::assertSame('name', $name->field);
        self::assertFalse($name->isDescending());
        self::assertSame('createdAt', $created->field);
        self::assertTrue($created->isDescending());
        self::assertSame('createdAt', (string) $created->reversed());
        self::assertSame('-name', (string) $name->reversed());
    }

    public function testTheDirectionIsTheOneOfPhp(): void
    {
        self::assertSame(SortDirection::Ascending, Sort::parse('name')->direction);
        self::assertSame(SortDirection::Descending, Sort::parse('-name')->direction);
        self::assertSame(SortDirection::Ascending, Sort::parse('-name')->reversed()->direction);
    }
}
