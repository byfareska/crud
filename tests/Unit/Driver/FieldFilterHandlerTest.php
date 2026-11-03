<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Driver;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineListQuery;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\DoctrineTextMatch;
use Byfareska\Crud\Driver\Doctrine\FieldFilter;
use Byfareska\Crud\Driver\Doctrine\FieldFilterHandler;
use Byfareska\Crud\Filter\Compared;
use Byfareska\Crud\Filter\Comparison;
use Byfareska\Crud\Filter\ComparisonType;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Person;
use Byfareska\Crud\Tests\Unit\Fixtures\NamedLike;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\ResolvedFormTypeFactory;

/**
 * A filter compared the bundle's ways, or one of the application's own; a text as the database compares it best.
 */
final class FieldFilterHandlerTest extends TestCase
{
    private EntityManager $entities;

    private FormFactoryInterface $forms;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $this->entities = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config),
            $config,
        );
        new SchemaTool($this->entities)->createSchema($this->entities->getMetadataFactory()->getAllMetadata());
        foreach (['Anna', 'Adam', 'Beata'] as $name) {
            $this->entities->persist(new Person($name));
        }
        $this->entities->flush();
        $this->forms = Forms::createFormFactory();
    }

    public function testAWayOfTheApplicationsOwn(): void
    {
        $filter = new FieldFilter('name', [Comparison::Contains, new NamedLike()]);

        self::assertSame(['Adam', 'Anna'], $this->names($filter, new Compared(new NamedLike(), 'A%')));
        self::assertSame(['Beata'], $this->names($filter, new Compared(Comparison::Contains, 'eat')));
    }

    public function testItIsChosenInTheFormByItsKey(): void
    {
        $handler = $this->handler();
        $filter = new FieldFilter('name', [Comparison::Contains, new NamedLike()]);
        $field = $handler->field($filter, 'name', [])->getForm();
        $field->submit(['comparison' => 'like', 'value' => 'B%']);

        self::assertEquals(new Compared(new NamedLike(), 'B%'), $field->getData());
        $view = $field->createView();
        self::assertSame([], $view->vars['many_comparisons']);
        self::assertSame([], $view->vars['presence_comparisons']);

        $choices = $handler->field(
            new FieldFilter('team', Comparison::CHOICES, ChoiceType::class, ['choices' => ['A' => 'a']]),
            'team',
            [],
        )->getForm()->createView();
        self::assertSame(['in', 'not_in'], $choices->vars['many_comparisons']);
        self::assertSame(['empty', 'filled'], $choices->vars['presence_comparisons']);
    }

    public function testATextIsComparedAsTheTextMatchSays(): void
    {
        // As typed: a column of a case-sensitive comparison, no LOWER().
        $exact = new readonly class implements DoctrineTextMatch {
            public function column(string $path): string
            {
                return $path;
            }

            public function text(string $typed): string
            {
                return $typed;
            }
        };
        $filter = new FieldFilter('name', Comparison::TEXT);

        self::assertSame(['Anna'], $this->names($filter, new Compared(Comparison::Equals, 'anna')));
        self::assertSame([], $this->names($filter, new Compared(Comparison::Equals, 'anna'), $exact));
        self::assertSame(['Anna'], $this->names($filter, new Compared(Comparison::Equals, 'Anna'), $exact));
    }

    /**
     * @return list<string>
     */
    private function names(FieldFilter $filter, Compared $value, ?DoctrineTextMatch $texts = null): array
    {
        $builder = $this->entities->createQueryBuilder()->select('e')->from(Person::class, 'e')->orderBy('e.name');
        $crud = new AsCrud('person', '/people', 'person', source: new DoctrineSource(Person::class));
        $query = new DoctrineListQuery(
            $crud,
            $builder,
            $this->entities->getClassMetadata(Person::class),
            $this->forms->create(ComparisonType::class, null, ['comparisons' => [Comparison::Equals]]),
        );
        $this->handler($texts)->apply($filter, $query, 'name', $value);

        /** @var list<Person> $people */
        $people = $builder->getQuery()->getResult();

        return array_map(static fn (Person $person): string => $person->name, $people);
    }

    private function handler(?DoctrineTextMatch $texts = null): FieldFilterHandler
    {
        $types = new FormRegistry([], new ResolvedFormTypeFactory());

        return $texts instanceof DoctrineTextMatch
            ? new FieldFilterHandler($this->forms, $types, $texts)
            : new FieldFilterHandler($this->forms, $types);
    }
}
