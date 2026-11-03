<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CrudDriver;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The driver of a resource, by the name its source gives - one of that name.
 */
final class CrudDriversTest extends TestCase
{
    public function testTheDriverOfItsName(): void
    {
        $doctrine = $this->driver('doctrine');

        self::assertSame($doctrine, new CrudDrivers([$this->driver('dto'), $doctrine])->of($this->crud()));
    }

    public function testTwoOfOneNameAreAMistake(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('decorate');

        new CrudDrivers([$this->driver('doctrine'), $this->driver('doctrine')])->of($this->crud());
    }

    private function crud(): AsCrud
    {
        return new AsCrud('thing', '/things', 'box', source: new DoctrineSource(stdClass::class));
    }

    private function driver(string $name): CrudDriver
    {
        $driver = self::createStub(CrudDriver::class);
        $driver->method('name')->willReturn($name);

        return $driver;
    }
}
