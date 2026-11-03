<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Resource;

use Byfareska\Crud\Resource\DefaultIdMapper;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DefaultIdMapperTest extends TestCase
{
    public function testScalarsAreTheirText(): void
    {
        $ids = new DefaultIdMapper();

        self::assertSame('7', $ids->toId(7));
        self::assertSame('abc', $ids->toId('abc'));
    }

    public function testAnIdObjectIsAsInJson(): void
    {
        // Like a Uuid: written in JSON as its text, though its __toString() may differ.
        $id = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return '0192f1c4-0000-7000-8000-000000000000';
            }
        };

        self::assertSame('0192f1c4-0000-7000-8000-000000000000', new DefaultIdMapper()->toId($id));
    }

    public function testNothingIsNoId(): void
    {
        $ids = new DefaultIdMapper();

        self::assertNull($ids->toId(null));
        self::assertNull($ids->toId(''));
        self::assertNull($ids->toId(new stdClass()));
        self::assertNull($ids->toId([1]));
    }
}
