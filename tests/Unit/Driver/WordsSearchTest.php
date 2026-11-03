<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineQuery;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\WordsSearch;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Order;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Person;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Team;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class WordsSearchTest extends TestCase
{
    private EntityManager $entities;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $this->entities = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        new SchemaTool($this->entities)->createSchema($this->entities->getMetadataFactory()->getAllMetadata());
        $sales = new Team('Sprzedaż');
        $this->entities->persist($sales);
        foreach (
            [
            [1024, 'Anna', 'Kraków', $sales],
            [10245, 'Adam', 'Gdańsk', null],
            ] as [$number, $name, $city, $team]
        ) {
            $person = new Person($name, $team);
            $order = new Order();
            $order->number = $number;
            $order->createdBy = $person;
            $order->address->city = $city;
            $this->entities->persist($person);
            $this->entities->persist($order);
        }
        $this->entities->flush();
    }

    public function testANumberIsLookedForAsAWholeNumber(): void
    {
        self::assertSame([1024], $this->found('1024'));
        self::assertSame([10245], $this->found('adam'), 'A word that is no number: in the texts only.');
        self::assertSame([], $this->found('1024 adam'), 'Every word, each in one of the fields.');
    }

    public function testAFieldOfAnEmbeddedObjectAndOneOfARelationFurtherAway(): void
    {
        self::assertSame([1024], $this->found('krak', ['address.city']));
        self::assertSame([1024], $this->found('sprzed', ['createdBy.team.name']));
    }

    public function testThePathOfAFieldJoinsEachRelationOnce(): void
    {
        $builder = $this->entities->createQueryBuilder()->select('e')->from(Order::class, 'e');
        $crud = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(Order::class));
        $query = new DoctrineQuery($crud, $builder, $this->entities->getClassMetadata(Order::class));

        self::assertSame('e.address.city', $query->path('address.city'));
        self::assertSame('r_createdBy_team.name', $query->path('createdBy.team.name'));
        self::assertSame('r_createdBy.name', $query->path('createdBy.name'));
        self::assertSame(['e', 'r_createdBy', 'r_createdBy_team'], $builder->getAllAliases());
        self::assertSame('string', $query->typeOf('createdBy.team.name'));
        self::assertTrue($query->knows('address.city'));
        self::assertFalse($query->knows('createdBy.nothing'));
    }

    public function testAWordInNoFieldItCouldBeFindsNothing(): void
    {
        self::assertSame([], $this->found('anna', ['number']));
    }

    /**
     * @param list<string> $fields
     *
     * @return list<int>
     */
    private function found(string $text, array $fields = ['number', 'createdBy.name']): array
    {
        $builder = $this->entities->createQueryBuilder()->select('e')->from(Order::class, 'e')->orderBy('e.number');
        $crud = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(Order::class));
        $query = new DoctrineQuery($crud, $builder, $this->entities->getClassMetadata(Order::class));
        new WordsSearch()->apply($query, $fields, $text);

        /** @var list<Order> $orders */
        $orders = $builder->getQuery()->getResult();

        return array_map(static fn (Order $order): int => $order->number, $orders);
    }
}
