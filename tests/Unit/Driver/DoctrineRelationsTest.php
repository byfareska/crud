<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineRelations;
use Byfareska\Crud\Driver\Doctrine\DoctrineScopes;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudResourceProvider;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Order;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Person;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The entities of a relation are offered in the scopes of a resource of their entity: the one the source names.
 */
final class DoctrineRelationsTest extends TestCase
{
    public function testTheResourceTheSourceNamesForTheRelation(): void
    {
        $orders = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(
            Order::class,
            relations: ['createdBy' => 'former_people'],
        ));
        $relations = $this->relations(
            $orders,
            new AsCrud('people', '/people', 'person', source: new DoctrineSource(Person::class)),
            new AsCrud('former_people', '/people/former', 'person', source: new DoctrineSource(Person::class)),
        );

        self::assertSame('former_people', $relations->resourceOf($orders, Person::class, 'createdBy')?->name);
        self::assertSame('people', $relations->resourceOf($orders, Person::class, 'approvedBy')?->name, 'Not named.');
        self::assertSame('people', $relations->resourceOf($orders, Person::class)?->name);
    }

    public function testAResourceOfAnotherEntityIsAMistake(): void
    {
        $orders = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(
            Order::class,
            relations: ['createdBy' => 'order'],
        ));

        $this->expectException(LogicException::class);

        $this->relations($orders)->resourceOf($orders, Person::class, 'createdBy');
    }

    private function relations(AsCrud ...$resources): DoctrineRelations
    {
        $provider = new readonly class (array_values($resources)) implements CrudResourceProvider {
            /** @param list<AsCrud> $resources */
            public function __construct(private array $resources)
            {
            }

            public function resources(): iterable
            {
                yield from $this->resources;
            }
        };
        $registry = new CrudRegistry(providers: [$provider]);

        return new DoctrineRelations($registry, self::createStub(ManagerRegistry::class), new DoctrineScopes());
    }
}
