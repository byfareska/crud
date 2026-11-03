<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Chart;

use Byfareska\Crud\Chart\NamedDayBucket;
use Byfareska\Crud\Chart\Sql\DoctrineDayBucket;
use Byfareska\Crud\Chart\Sql\MySqlDayBucket;
use Byfareska\Crud\Chart\Sql\PostgreSqlDayBucket;
use Byfareska\Crud\Chart\Sql\SqliteDayBucket;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class DayBucketChoiceTest extends TestCase
{
    public function testTheNamedOneIsTheOneOfThatIndex(): void
    {
        $bucket = new NamedDayBucket('postgresql', new ServiceLocator([
            'postgresql' => static fn (): PostgreSqlDayBucket => new PostgreSqlDayBucket(),
        ]));

        self::assertStringStartsWith('to_char(', $bucket->sql('created_at', new DateTimeZone('UTC')));
    }

    public function testAnUnknownNameIsAMistake(): void
    {
        $this->expectException(LogicException::class);

        new NamedDayBucket('oracle', new ServiceLocator([]))->sql('created_at', new DateTimeZone('UTC'));
    }

    public function testDoctrinePicksTheOneThatSupportsItsPlatform(): void
    {
        $bucket = new DoctrineDayBucket(
            $this->connectionTo(new SQLitePlatform()),
            [new PostgreSqlDayBucket(), new MySqlDayBucket(), new SqliteDayBucket()],
        );

        self::assertSame("date(created_at, '+0 seconds')", $bucket->sql('created_at', new DateTimeZone('UTC')));
    }

    public function testDoctrineWithoutOneForItsPlatformIsAMistake(): void
    {
        $this->expectException(LogicException::class);

        new DoctrineDayBucket(
            $this->connectionTo(new OraclePlatform()),
            [new SqliteDayBucket()],
        )->sql('created_at', new DateTimeZone('UTC'));
    }

    private function connectionTo(object $platform): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        return $connection;
    }
}
