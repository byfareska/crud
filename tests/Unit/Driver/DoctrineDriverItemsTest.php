<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\ChoiceRelationFilterField;
use Byfareska\Crud\Driver\Doctrine\ConstructorEntityFactory;
use Byfareska\Crud\Driver\Doctrine\DefaultDoctrineExceptionMapper;
use Byfareska\Crud\Driver\Doctrine\DoctrineCount;
use Byfareska\Crud\Driver\Doctrine\DoctrineDriver;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityNormalizer;
use Byfareska\Crud\Driver\Doctrine\DoctrineItems;
use Byfareska\Crud\Driver\Doctrine\DoctrineListExtension;
use Byfareska\Crud\Driver\Doctrine\DoctrineListQuery;
use Byfareska\Crud\Driver\Doctrine\DoctrineManagers;
use Byfareska\Crud\Driver\Doctrine\DoctrineRelations;
use Byfareska\Crud\Driver\Doctrine\DoctrineScopes;
use Byfareska\Crud\Driver\Doctrine\DoctrineSearches;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\FieldFilterHandler;
use Byfareska\Crud\Driver\Doctrine\FieldSortHandler;
use Byfareska\Crud\Driver\Doctrine\FlushingPersister;
use Byfareska\Crud\Driver\Doctrine\MappingFilterGuesser;
use Byfareska\Crud\Driver\Doctrine\WordsSearch;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\DefaultPageSizes;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Person;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Team;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\PlainValueNormalizer;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validation;

/**
 * What an item of a list has besides the fields of its entity: what the query selects, what a service adds.
 */
final class DoctrineDriverItemsTest extends TestCase
{
    public function testAColumnTheQuerySelectsAndOneAServiceAddsAPageAtOnce(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $entities = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        new SchemaTool($entities)->createSchema($entities->getMetadataFactory()->getAllMetadata());
        $sales = new Team('Sprzedaż');
        $entities->persist($sales);
        foreach (['Anna', 'Adam'] as $name) {
            $entities->persist(new Person($name, $sales));
        }
        $entities->flush();

        // The length of the name, counted by the database.
        $length = new readonly class implements DoctrineListExtension {
            public function resource(): string
            {
                return 'person';
            }

            public function apply(DoctrineListQuery $query): void
            {
                $query->query->addSelect('LENGTH(e.name) AS nameLength');
            }
        };
        $pages = new class implements DoctrineItems {
            public int $calls = 0;

            public function resource(): string
            {
                return DoctrineItems::EVERY;
            }

            public function extend(AsCrud $crud, array $entities, array $items): array
            {
                ++$this->calls;
                foreach ($items as $index => $item) {
                    $items[$index]['initial'] = $entities[$index] instanceof Person ? $entities[$index]->name[0] : '';
                }

                return $items;
            }
        };
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($entities);
        $driver = $this->driver($doctrine, [$length], [$pages]);
        $crud = new AsCrud('person', '/people', 'person', source: new DoctrineSource(Person::class), columns: ['name']);

        $form = $driver->listForm($crud);
        $form->submit(['page' => '1', 'perPage' => '10']);
        $page = $driver->list($crud, $form);

        self::assertSame(['Anna', 'Adam'], array_column($page->items, 'name'));
        self::assertEquals([4, 4], array_column($page->items, 'nameLength'));
        self::assertSame(['A', 'A'], array_column($page->items, 'initial'));
        self::assertSame(1, $pages->calls, 'Once for the page, not for each row.');
        $first = $page->items[0]['id'] ?? null;
        self::assertIsString($first);
        self::assertSame('A', $driver->read($crud, $first)['initial']);
        // Every match (a bulk action), by the ids alone - the select of the extension does not get in the way.
        $ids = $driver->ids($crud, $form);
        self::assertNotNull($ids);
        self::assertEquals(array_column($page->items, 'id'), iterator_to_array($ids, false));
    }

    public function testARecordReadInATransactionOfALockingSourceIsFound(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $entities = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        new SchemaTool($entities)->createSchema($entities->getMetadataFactory()->getAllMetadata());
        $anna = new Person('Anna');
        $entities->persist($anna);
        $entities->flush();
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($entities);
        $crud = new AsCrud('person', '/people', 'person', source: new DoctrineSource(Person::class, lock: true));
        $driver = $this->driver($doctrine, [], []);

        // Outside a transaction no lock is asked for (it would need one); inside, the row is locked for it.
        $outside = $driver->read($crud, (string) $anna->id);
        $entities->getConnection()->beginTransaction();
        $inside = $driver->read($crud, (string) $anna->id);
        $entities->getConnection()->rollBack();
        self::assertSame(['Anna', 'Anna'], [$outside['name'] ?? null, $inside['name'] ?? null]);
    }

    public function testAListCountedByACountOfItsOwn(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $entities = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        new SchemaTool($entities)->createSchema($entities->getMetadataFactory()->getAllMetadata());
        foreach (['Anna', 'Adam', 'Beata'] as $name) {
            $entities->persist(new Person($name));
        }
        $entities->flush();
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($entities);
        // An estimate while nothing filters the list, as of a table of millions.
        $estimate = new readonly class implements DoctrineCount {
            public function count(DoctrineListQuery $query): array
            {
                return $query->filtered() ? [1, false] : [1000000, true];
            }
        };
        $driver = $this->driver($doctrine, [], [], [$estimate::class => static fn (): DoctrineCount => $estimate]);
        $crud = new AsCrud('person', '/people', 'person', source: new DoctrineSource(
            Person::class,
            search: ['name'],
            countWith: $estimate::class,
        ));

        $all = $driver->listForm($crud);
        $all->submit(['page' => '1', 'perPage' => '2']);
        $page = $driver->list($crud, $all);
        self::assertSame(1000000, $page->total);
        self::assertCount(2, $page->items, 'Paged as ever.');

        $found = $driver->listForm($crud);
        $found->submit(['page' => '1', 'perPage' => '2', 'query' => 'Beata']);
        self::assertSame(1, $driver->list($crud, $found)->total);
    }

    /**
     * @param list<DoctrineListExtension> $extensions
     * @param list<DoctrineItems> $items
     * @param array<string, callable(): DoctrineCount> $counters
     */
    private function driver(
        ManagerRegistry $doctrine,
        array $extensions,
        array $items,
        array $counters = [],
    ): DoctrineDriver {
        $forms = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
        $normalizer = new DoctrineEntityNormalizer($doctrine);
        $registry = new CrudRegistry();

        return new DoctrineDriver(
            $doctrine,
            $forms,
            PropertyAccess::createPropertyAccessor(),
            new CrudValues([$normalizer, new PlainValueNormalizer()]),
            $normalizer,
            new MappingFilterGuesser(
                new FormRegistry([], new ResolvedFormTypeFactory()),
                new ChoiceRelationFilterField(),
            ),
            new DoctrineScopes(),
            new ServiceLocator([
                FieldFilterHandler::class => static fn (): FieldFilterHandler => new FieldFilterHandler(
                    $forms,
                    new FormRegistry([], new ResolvedFormTypeFactory()),
                ),
            ]),
            new ServiceLocator([FieldSortHandler::class => static fn (): FieldSortHandler => new FieldSortHandler()]),
            new DoctrineSearches(new WordsSearch(), new ServiceLocator([])),
            new DefaultPageSizes(),
            new DoctrineRelations($registry, $doctrine, new DoctrineScopes()),
            new ConstructorEntityFactory(),
            new FlushingPersister(),
            new DefaultDoctrineExceptionMapper(),
            new DoctrineManagers($doctrine),
            $extensions,
            itemExtensions: $items,
            counters: new ServiceLocator($counters),
        );
    }
}
