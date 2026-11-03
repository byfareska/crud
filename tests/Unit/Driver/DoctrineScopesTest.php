<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use ArrayObject;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineQuery;
use Byfareska\Crud\Driver\Doctrine\DoctrineScope;
use Byfareska\Crud\Driver\Doctrine\DoctrineScopes;
use Byfareska\Crud\Tests\Unit\Fixtures\ListThingsDto;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use stdClass;

final class DoctrineScopesTest extends TestCase
{
    public function testAResourceGetsItsOwnScopesAndThoseOfEveryResource(): void
    {
        $applied = new ArrayObject();
        $scopes = new DoctrineScopes([
            $this->scope('thing', $applied),
            $this->scope('other', $applied),
            $this->scope(DoctrineScope::EVERY, $applied),
        ]);

        $scopes->apply($this->query());

        self::assertSame(['thing', DoctrineScope::EVERY], $applied->getArrayCopy());
    }

    public function testAnEntityWithoutAResourceGetsOnlyTheScopesOfEveryResource(): void
    {
        $applied = new ArrayObject();
        $scopes = new DoctrineScopes([
            $this->scope('thing', $applied),
            $this->scope(DoctrineScope::EVERY, $applied),
        ]);

        $scopes->applyEvery($this->query());

        self::assertSame([DoctrineScope::EVERY], $applied->getArrayCopy());
    }

    /**
     * @param ArrayObject<int, string> $applied
     */
    private function scope(string $resource, ArrayObject $applied): DoctrineScope
    {
        return new readonly class ($resource, $applied) implements DoctrineScope {
            /**
             * @param ArrayObject<int, string> $applied
             */
            public function __construct(private string $resource, private ArrayObject $applied)
            {
            }

            public function resource(): string
            {
                return $this->resource;
            }

            public function apply(DoctrineQuery $query): void
            {
                $this->applied[] = $this->resource;
            }
        };
    }

    private function query(): DoctrineQuery
    {
        $crud = new AsCrud(name: 'thing', path: '/things', icon: 'box', list: ListThingsDto::class);

        return new DoctrineQuery(
            $crud,
            new QueryBuilder(self::createStub(EntityManagerInterface::class)),
            new ClassMetadata(stdClass::class),
        );
    }
}
