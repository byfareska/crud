<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineManagers;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Order;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Person;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The related entities of a resource come from the database its records come from: a tenant's, not the first one.
 */
final class DoctrineManagersTest extends TestCase
{
    public function testTheManagerOfTheSourceWhenItMapsTheEntity(): void
    {
        $default = $this->manager();
        $tenant = $this->manager();
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($default);
        $doctrine->method('getManager')->willReturnCallback(
            static fn (?string $name): EntityManager => $name === 'tenant_a' ? $tenant : $default,
        );
        $managers = new DoctrineManagers($doctrine);

        $orders = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(Order::class, manager: 'tenant_a'));
        self::assertSame($tenant, $managers->of($orders));
        self::assertSame($tenant, $managers->of($orders, Person::class), 'A relation, in the same database.');

        $everywhere = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(Order::class));
        self::assertSame($default, $managers->of($everywhere, Person::class));
    }

    public function testAnEntityTheManagerOfTheSourceDoesNotMapIsOfItsOwn(): void
    {
        $default = $this->manager();
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($default);
        $doctrine->method('getManager')->willReturn($this->manager());
        $orders = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(Order::class, manager: 'tenant_a'));

        self::assertSame($default, new DoctrineManagers($doctrine)->of($orders, stdClass::class));
    }

    private function manager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);

        return new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
    }
}
