<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DefaultDoctrineIdCodec;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityNormalizer;
use Byfareska\Crud\Driver\Doctrine\DoctrineRelations;
use Byfareska\Crud\Driver\Doctrine\DoctrineScopes;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Order;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\OrderLine;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\PlainValueNormalizer;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

/**
 * The id of an entity in an address and back: of one field as it is, of several fields each part encoded.
 */
final class DoctrineIdCodecTest extends TestCase
{
    private EntityManager $entities;

    private CrudValues $values;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $this->entities = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        new SchemaTool($this->entities)->createSchema($this->entities->getMetadataFactory()->getAllMetadata());
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->entities);
        $this->values = new CrudValues([new DoctrineEntityNormalizer($doctrine), new PlainValueNormalizer()]);
    }

    public function testAnIdOfOneFieldIsAsItIs(): void
    {
        $codec = new DefaultDoctrineIdCodec();
        $order = new Order();
        $order->id = 12;
        $metadata = $this->entities->getClassMetadata(Order::class);

        self::assertSame('12', $codec->encode($metadata, $order, $this->values));
        self::assertSame(['id' => '12'], $codec->decode($metadata, '12'));
        self::assertSame(['id' => '-1'], $codec->decode($metadata, '-1'), 'A negative number is a number.');
        self::assertNull($codec->decode($metadata, 'abc'), 'No number: nothing of a field of numbers.');
        self::assertNull($codec->encode($metadata, new Order(), $this->values), 'Not persisted: no id.');
    }

    public function testAnIdOfSeveralFieldsIsReadBackAsItWas(): void
    {
        $codec = new DefaultDoctrineIdCodec();
        $order = new Order();
        $order->id = 12;
        $metadata = $this->entities->getClassMetadata(OrderLine::class);
        // A part may have the separator, or a "-" of a uuid, in it.
        $line = new OrderLine($order, 3, 'red,large-2');

        $id = $codec->encode($metadata, $line, $this->values);

        self::assertSame('12,3,red%2Clarge-2', $id);
        self::assertSame(
            ['order' => '12', 'number' => '3', 'variant' => 'red,large-2'],
            $codec->decode($metadata, $id),
        );
        self::assertNull($codec->decode($metadata, '12,3'), 'A part missing.');
        self::assertNull($codec->decode($metadata, '12,x,red'), 'No number for a field of numbers.');
    }

    public function testAnEntityOfAnIdOfSeveralFieldsIsFound(): void
    {
        $order = new Order();
        $this->entities->persist($order);
        $this->entities->flush();
        $this->entities->persist(new OrderLine($order, 1, 'a'));
        $this->entities->persist(new OrderLine($order, 2, 'b,c'));
        $this->entities->flush();
        $this->entities->clear();
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->entities);
        $relations = new DoctrineRelations(new CrudRegistry(), $doctrine, new DoctrineScopes());
        $orders = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(Order::class));

        $found = $relations->find($orders, OrderLine::class, $order->id . ',2,b%2Cc');

        self::assertInstanceOf(OrderLine::class, $found);
        self::assertSame([2, 'b,c'], [$found->number, $found->variant]);
        self::assertNull($relations->find($orders, OrderLine::class, $order->id . ',2,a'));
    }
}
