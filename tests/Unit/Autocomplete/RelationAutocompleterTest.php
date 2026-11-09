<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Autocomplete;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Autocomplete\RelationAutocompleter;
use Byfareska\Crud\Autocomplete\RelationChoiceLabel;
use Byfareska\Crud\Autocomplete\RelationChoiceLabels;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityNormalizer;
use Byfareska\Crud\Driver\Doctrine\DoctrineRelations;
use Byfareska\Crud\Driver\Doctrine\DoctrineScopes;
use Byfareska\Crud\Driver\Doctrine\DoctrineSearches;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\RelationFilterField;
use Byfareska\Crud\Driver\Doctrine\FieldFilter;
use Byfareska\Crud\Driver\Doctrine\WordsSearch;
use Byfareska\Crud\Filter\Comparison;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudResourceProvider;
use Byfareska\Crud\Routing\CrudRouteLoader;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Order;
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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\UX\Autocomplete\Controller\AutocompleteController;

final class RelationAutocompleterTest extends TestCase
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
        $this->entities->persist(new Person('Anna', $sales));
        $this->entities->persist(new Person('Adam'));
        $this->entities->flush();
    }

    public function testAFilterOfAnotherNameThanItsRelation(): void
    {
        $autocompleter = $this->autocompleter('createdBy');

        self::assertTrue($autocompleter->isGranted(self::createStub(Security::class)));
        // The people: two of them.
        self::assertCount(2, $this->found($autocompleter));
    }

    public function testARelationFurtherAway(): void
    {
        $autocompleter = $this->autocompleter('team');

        self::assertTrue($autocompleter->isGranted(self::createStub(Security::class)));
        // The teams of the people: one.
        self::assertCount(1, $this->found($autocompleter));
    }

    public function testARelationNoFilterIsAboutIsRefused(): void
    {
        self::assertFalse($this->autocompleter('orders')->isGranted(self::createStub(Security::class)));
    }

    public function testTheFilterThatAsksIsTheOneOfItsName(): void
    {
        // "team" is the relation of the filter "team"; the filter "author" is of another one.
        self::assertCount(1, $this->found($this->autocompleter('team', 'team')));
        self::assertFalse($this->autocompleter('team', 'author')->isGranted(self::createStub(Security::class)));
    }

    public function testAPageOfItsSizeAndTheTextsOfTheApplication(): void
    {
        $autocompleter = $this->autocompleter('createdBy', labels: new RelationChoiceLabels([
            new readonly class implements RelationChoiceLabel {
                public function supports(object $entity): bool
                {
                    return $entity instanceof Person;
                }

                public function label(object $entity): string
                {
                    return $entity instanceof Person ? 'Osoba: ' . $entity->name : '';
                }
            },
        ]), perPage: 1);

        $results = $autocompleter->fetchResults('', 1);

        self::assertCount(1, $results->results, 'One a page.');
        self::assertTrue($results->hasNextPage);
        self::assertStringStartsWith('Osoba: ', (string) $results->results[0]['text']);
    }

    public function testOnlyTheAddressOfThePanelIsAnswered(): void
    {
        // The global /autocomplete of symfony/ux-autocomplete, or the address of another panel: outside the firewall
        // and access_control of the panel of the resource.
        foreach ([[], [CrudRouteLoader::AUTOCOMPLETE => true, Scope::ATTRIBUTE => 'admin']] as $attributes) {
            $autocompleter = $this->autocompleter('createdBy', request: new Request(attributes: $attributes));

            self::assertFalse($autocompleter->isGranted(self::createStub(Security::class)));
            try {
                $autocompleter->fetchResults('', 1);
                self::fail('Searched outside the panel.');
            } catch (AccessDeniedHttpException) {
                // Refused.
            }
        }
    }

    private function autocompleter(
        string $field,
        ?string $filter = null,
        ?RelationChoiceLabels $labels = null,
        ?int $perPage = null,
        ?Request $request = null,
    ): RelationAutocompleter {
        $crud = new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(Order::class, filters: [
            'author' => new FieldFilter('createdBy', Comparison::CHOICE),
            'team' => new FieldFilter('createdBy.team', Comparison::CHOICE),
        ]));
        $registry = new CrudRegistry(providers: [new readonly class ($crud) implements CrudResourceProvider {
            public function __construct(private AsCrud $crud)
            {
            }

            public function resources(): iterable
            {
                yield $this->crud;
            }
        }]);
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->entities);
        $access = self::createStub(CrudAccess::class);
        $access->method('isGranted')->willReturn(true);

        $normalizer = new DoctrineEntityNormalizer($doctrine);
        $autocompleter = new RelationAutocompleter(
            $registry,
            $access,
            $doctrine,
            new CrudValues([$normalizer, new PlainValueNormalizer()]),
            $normalizer,
            new DoctrineRelations($registry, $doctrine, new DoctrineScopes()),
            new DoctrineSearches(new WordsSearch(), new ServiceLocator([])),
            $labels,
            requests: $this->requests($request ?? new Request(attributes: [
                // Asked through the autocomplete route of the panel of the resource (CrudRouteLoader).
                CrudRouteLoader::AUTOCOMPLETE => true,
                Scope::ATTRIBUTE => $crud->scope,
            ])),
        );
        $autocompleter->setOptions([AutocompleteController::EXTRA_OPTIONS => array_filter([
            RelationAutocompleter::RESOURCE => 'order',
            RelationAutocompleter::FIELD => $field,
            RelationFilterField::FILTER => $filter,
            RelationAutocompleter::PER_PAGE => $perPage,
        ])]);

        return $autocompleter;
    }

    private function requests(Request $request): RequestStack
    {
        $requests = new RequestStack();
        $requests->push($request);

        return $requests;
    }

    /**
     * @return list<mixed>
     */
    private function found(RelationAutocompleter $autocompleter): array
    {
        return array_column($autocompleter->fetchResults('', 1)->results, 'value');
    }
}
