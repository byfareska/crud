<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Autocomplete;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Autocomplete\AutocompletedFormFields;
use Byfareska\Crud\Autocomplete\AutocompleteRelationFilterField;
use Byfareska\Crud\Autocomplete\AutocompleteRelationFormField;
use Byfareska\Crud\Autocomplete\RelationAutocompleter;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Driver\Doctrine\ChoiceRelationFilterField;
use Byfareska\Crud\Driver\Doctrine\ChoiceRelationFormField;
use Byfareska\Crud\Driver\Doctrine\ConstructorEntityFactory;
use Byfareska\Crud\Driver\Doctrine\DefaultDoctrineExceptionMapper;
use Byfareska\Crud\Driver\Doctrine\DoctrineDriver;
use Byfareska\Crud\Driver\Doctrine\DoctrineEntityNormalizer;
use Byfareska\Crud\Driver\Doctrine\DoctrineFilter;
use Byfareska\Crud\Driver\Doctrine\DoctrineManagers;
use Byfareska\Crud\Driver\Doctrine\DoctrineQuery;
use Byfareska\Crud\Driver\Doctrine\DoctrineRecordLabels;
use Byfareska\Crud\Driver\Doctrine\DoctrineRelations;
use Byfareska\Crud\Driver\Doctrine\DoctrineScope;
use Byfareska\Crud\Driver\Doctrine\DoctrineScopes;
use Byfareska\Crud\Driver\Doctrine\DoctrineSearches;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;
use Byfareska\Crud\Driver\Doctrine\FieldFilter;
use Byfareska\Crud\Driver\Doctrine\FieldFilterHandler;
use Byfareska\Crud\Driver\Doctrine\FieldSortHandler;
use Byfareska\Crud\Driver\Doctrine\FlushingPersister;
use Byfareska\Crud\Driver\Doctrine\MappingFilterGuesser;
use Byfareska\Crud\Driver\Doctrine\RelationFilterField;
use Byfareska\Crud\Driver\Doctrine\WordsSearch;
use Byfareska\Crud\Filter\Comparison;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\CrudFormExtension;
use Byfareska\Crud\Resource\CrudLayouts;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudResourceProvider;
use Byfareska\Crud\Resource\DefaultCrudRecords;
use Byfareska\Crud\Resource\DefaultPageSizes;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Routing\CrudRouteLoader;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Meeting;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Order;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Person;
use Byfareska\Crud\Tests\Unit\Fixtures\Entity\Team;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\PlainValueNormalizer;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validation;
use Symfony\UX\Autocomplete\Checksum\ChecksumCalculator;
use Symfony\UX\Autocomplete\Controller\AutocompleteController;
use Symfony\UX\Autocomplete\Form\AutocompleteChoiceTypeExtension;

/**
 * The relations of guessed forms and the relation filters of lists offer - and take when sent - only the entities the
 * scopes of the related resource leave, and a copy takes no other; the searches of a form answer only one whose form
 * has the field; a filter of options of the application's own is no field searched by the bundle.
 */
final class RelationFieldsTest extends TestCase
{
    private EntityManager $entities;

    private ManagerRegistry $doctrine;

    private Person $anna;

    private Person $adam;

    private Person $bob;

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
        // Only Anna is in the scope of "person" (of a team): Adam and Bob are somebody else's - another tenant's.
        $this->anna = new Person('Anna', $sales);
        $this->adam = new Person('Adam');
        $this->bob = new Person('Bob');
        foreach ([$this->anna, $this->adam, $this->bob] as $person) {
            $this->entities->persist($person);
        }
        $this->entities->flush();
        $doctrine = self::createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($this->entities);
        $doctrine->method('getManagers')->willReturn(['default' => $this->entities]);
        $doctrine->method('getManager')->willReturn($this->entities);
        $this->doctrine = $doctrine;
    }

    public function testAGuessedRelationTakesNoEntityTheScopesLeaveOut(): void
    {
        foreach ([false, true] as $autocomplete) {
            $driver = $this->driver($autocomplete);

            $sent = $this->submitted($driver->form($this->orders(), null), (string) $this->adam->id);
            self::assertFalse($sent->isValid(), 'An id of another tenant, sent by hand.');

            $sent = $this->submitted($driver->form($this->orders(), null), (string) $this->anna->id);
            self::assertTrue($sent->isValid(), (string) $sent->getErrors(true));
            self::assertInstanceOf(Order::class, $sent->getData());
            self::assertSame($this->anna, $sent->getData()->createdBy);
        }
    }

    public function testThePlainChoiceOffersOnlyWhatTheScopesLeave(): void
    {
        $form = $this->driver(false)->form($this->orders(), null)->getForm();

        self::assertSame(['Anna'], $this->choices($form->get('createdBy')));
    }

    public function testWhatTheRecordHasStaysItsValueButNoOtherIsTaken(): void
    {
        // Adam was chosen before the scopes left him out: the edit form shows and keeps him, takes no Bob.
        $order = new Order();
        $order->createdBy = $this->adam;
        $this->entities->persist($order);
        $this->entities->flush();
        $id = (string) $order->id;
        $driver = $this->driver(false);

        $edited = $driver->form($this->orders(), $id)->getForm();
        self::assertSame(['Anna', 'Adam'], $this->choices($edited->get('createdBy')));
        self::assertTrue($this->submitted($driver->form($this->orders(), $id), (string) $this->adam->id)->isValid());
        self::assertFalse($this->submitted($driver->form($this->orders(), $id), (string) $this->bob->id)->isValid());
    }

    public function testARelationOfManyKeepsWhatTheRecordHasButTakesNoOther(): void
    {
        // Adam came to the meeting before the scopes left him out: he stays, Anna may be added, Bob may not.
        $meeting = new Meeting();
        $meeting->attendees->add($this->adam);
        $this->entities->persist($meeting);
        $this->entities->flush();
        $id = (string) $meeting->id;
        $meetings = $this->meetings();
        $driver = $this->driver(false, $this->registry($meetings, $this->people()));

        $edited = $driver->form($meetings, $id)->getForm();
        self::assertSame(['Anna', 'Adam'], $this->choices($edited->get('attendees')));
        $kept = $this->sent($driver->form($meetings, $id), ['attendees' => [(string) $this->adam->id]]);
        self::assertTrue($kept->isValid(), (string) $kept->getErrors(true));
        $added = $this->sent($driver->form($meetings, $id), [
            'attendees' => [(string) $this->adam->id, (string) $this->anna->id],
        ]);
        self::assertTrue($added->isValid(), (string) $added->getErrors(true));

        // Sent by hand: another one the scopes leave out, next to the one the record has - or instead of it.
        $adam = (string) $this->adam->id;
        $bob = (string) $this->bob->id;
        foreach ([[$adam, $bob], [$bob]] as $attendees) {
            $this->entities->clear();
            $sent = $this->sent($driver->form($meetings, $id), ['attendees' => $attendees]);
            self::assertFalse($sent->isValid(), 'Bob, of another tenant, sent by hand: ' . implode(',', $attendees));
            $data = $sent->getData();
            self::assertInstanceOf(Meeting::class, $data);
            self::assertNotContains($bob, array_map(
                static fn (Person $person): string => (string) $person->id,
                $data->attendees->toArray(),
            ));
        }
    }

    public function testACopyTakesOfARelationOnlyWhatTheScopesLeaveNow(): void
    {
        // Adam was chosen before the scopes left him out: a copy is no way to choose him again.
        $byAdam = new Order();
        $byAdam->createdBy = $this->adam;
        $byAnna = new Order();
        $byAnna->createdBy = $this->anna;
        $meeting = new Meeting();
        $meeting->attendees->add($this->adam);
        $meeting->attendees->add($this->anna);
        foreach ([$byAdam, $byAnna, $meeting] as $entity) {
            $this->entities->persist($entity);
        }
        $this->entities->flush();
        $orders = $this->orders();
        $meetings = $this->meetings();
        $driver = $this->driver(false, $this->registry($orders, $meetings, $this->people()));

        foreach ([[$byAdam, null], [$byAnna, $this->anna]] as [$original, $copied]) {
            $form = $driver->form($orders, null);
            $driver->copy($orders, (string) $original->id, $form, ['createdBy']);
            $copy = $form->getData();
            self::assertInstanceOf(Order::class, $copy);
            self::assertSame($copied, $copy->createdBy);
        }

        $form = $driver->form($meetings, null);
        $driver->copy($meetings, (string) $meeting->id, $form, ['attendees']);
        $copy = $form->getData();
        self::assertInstanceOf(Meeting::class, $copy);
        self::assertSame([$this->anna], array_values($copy->attendees->toArray()));
        self::assertCount(2, $meeting->attendees, 'The original keeps its own.');
    }

    public function testARelationFilterOffersOnlyWhatTheScopesLeave(): void
    {
        foreach ([false, true] as $autocomplete) {
            $filter = $this->driver($autocomplete)->listForm($this->orders())->get('author')->get('value');
            $builder = $filter->getConfig()->getOption('query_builder');

            self::assertInstanceOf(QueryBuilder::class, $builder);
            self::assertSame([$this->anna], $builder->getQuery()->getResult());
        }
    }

    public function testAFilterOfTheApplicationsOwnChoiceIsNoFieldSearchedByTheBundle(): void
    {
        $orders = $this->orders(['author' => new FieldFilter('createdBy', Comparison::CHOICE, options: [
            'choice_label' => 'name',
            'options_as_html' => true,
        ])]);
        $value = $this->driver(true)->listForm($orders)->get('author')->get('value')->getConfig();

        self::assertFalse($value->getOption('autocomplete'), 'A plain choice by its own options.');
        self::assertSame([], $value->getOption('extra_options'));
        self::assertSame('name', $value->getOption('choice_label'));
        // Still in the scopes, having no query_builder of its own.
        $builder = $value->getOption('query_builder');
        self::assertInstanceOf(QueryBuilder::class, $builder);
        self::assertSame([$this->anna], $builder->getQuery()->getResult());

        // Nor is it searched by an address made before, which would follow none of those options.
        $autocompleter = $this->autocompleter($orders, [
            RelationAutocompleter::RESOURCE => 'order',
            RelationAutocompleter::FIELD => 'createdBy',
            RelationFilterField::FILTER => 'author',
        ]);
        self::assertFalse($autocompleter->isGranted(self::createStub(Security::class)));

        // A guessed one is the bundle's field to search in.
        $guessed = $this->driver(true)->listForm($this->orders())->get('author')->get('value')->getConfig();
        self::assertTrue($guessed->getOption('autocomplete'));
    }

    public function testAFormIsSearchedOnlyByOneWhoseFormHasTheField(): void
    {
        $extra = [
            RelationAutocompleter::RESOURCE => 'order',
            RelationAutocompleter::FIELD => 'createdBy',
            RelationAutocompleter::FORM => '1',
        ];
        $security = self::createStub(Security::class);

        self::assertSame([(string) $this->anna->id], $this->found($this->autocompleter($this->orders(), $extra)));

        // Not in the form at all (its fields), removed or disabled by an extension for the one asking.
        $without = $this->orders(fields: ['number']);
        self::assertFalse($this->autocompleter($without, $extra)->isGranted($security));
        foreach (['remove', 'disable'] as $change) {
            $autocompleter = $this->autocompleter($this->orders(), $extra, [$this->extension($change)]);
            self::assertFalse($autocompleter->isGranted($security), $change);
            try {
                $autocompleter->fetchResults('', 1);
                self::fail('Searched a relation the form keeps from the one asking: ' . $change);
            } catch (AccessDeniedHttpException) {
                // Refused.
            }
        }

        // The edit form of a record has it; of a record that is not there (or out of the scopes) it has not.
        $order = new Order();
        $this->entities->persist($order);
        $this->entities->flush();
        $edit = $this->autocompleter($this->orders(), [RelationAutocompleter::RECORD => (string) $order->id] + $extra);
        self::assertTrue($edit->isGranted($security));
        $missing = $this->autocompleter($this->orders(), [RelationAutocompleter::RECORD => '999'] + $extra);
        self::assertFalse($missing->isGranted($security));
    }

    /**
     * @param array<string, DoctrineFilter|string> $filters
     * @param list<string> $fields
     */
    private function orders(array $filters = [], array $fields = ['createdBy']): AsCrud
    {
        return new AsCrud('order', '/orders', 'cart', source: new DoctrineSource(
            Order::class,
            fields: $fields,
            filters: $filters === [] ? ['author' => new FieldFilter('createdBy', Comparison::CHOICE)] : $filters,
        ));
    }

    private function meetings(): AsCrud
    {
        return new AsCrud('meeting', '/meetings', 'people', source: new DoctrineSource(
            Meeting::class,
            fields: ['attendees'],
        ));
    }

    private function people(): AsCrud
    {
        return new AsCrud('person', '/people', 'person', source: new DoctrineSource(Person::class), columns: ['name']);
    }

    private function registry(AsCrud ...$resources): CrudRegistry
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

        return new CrudRegistry(providers: [$provider]);
    }

    private function scopes(): DoctrineScopes
    {
        return new DoctrineScopes([new readonly class implements DoctrineScope {
            public function resource(): string
            {
                return 'person';
            }

            public function apply(DoctrineQuery $query): void
            {
                $query->query->andWhere('e.team IS NOT NULL');
            }
        }]);
    }

    private function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new DoctrineOrmExtension($this->doctrine))
            ->addTypeExtension(new AutocompleteChoiceTypeExtension(new ChecksumCalculator('test')))
            ->getFormFactory();
    }

    private function driver(bool $autocomplete, ?CrudRegistry $registry = null): DoctrineDriver
    {
        $forms = $this->forms();
        $registry ??= $this->registry($this->orders(), $this->people());
        $values = new CrudValues([new DoctrineEntityNormalizer($this->doctrine), new PlainValueNormalizer()]);
        $labels = new DoctrineRecordLabels(
            $this->doctrine,
            $registry,
            PropertyAccess::createPropertyAccessor(),
            $values,
        );
        $urls = self::createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/admin/_crud/autocomplete/byfareska_crud_relation');
        $filterField = $autocomplete
            ? new AutocompleteRelationFilterField($urls)
            : new ChoiceRelationFilterField($labels);
        $formField = $autocomplete
            ? new AutocompleteRelationFormField($urls, $labels)
            : new ChoiceRelationFormField($labels);
        $types = new FormRegistry([new DoctrineOrmExtension($this->doctrine)], new ResolvedFormTypeFactory());

        return new DoctrineDriver(
            $this->doctrine,
            $forms,
            PropertyAccess::createPropertyAccessor(),
            $values,
            new DoctrineEntityNormalizer($this->doctrine),
            new MappingFilterGuesser($types, $filterField),
            $this->scopes(),
            new ServiceLocator([
                FieldFilterHandler::class => static fn (): FieldFilterHandler => new FieldFilterHandler($forms, $types),
            ]),
            new ServiceLocator([FieldSortHandler::class => static fn (): FieldSortHandler => new FieldSortHandler()]),
            new DoctrineSearches(new WordsSearch(), new ServiceLocator([])),
            new DefaultPageSizes(),
            new DoctrineRelations($registry, $this->doctrine, $this->scopes()),
            new ConstructorEntityFactory(),
            new FlushingPersister(),
            new DefaultDoctrineExceptionMapper(),
            new DoctrineManagers($this->doctrine),
            labels: $labels,
            relationFields: $formField,
        );
    }

    /**
     * @param array<string, string> $extra the extra_options of the field, as signed
     * @param list<CrudFormExtension> $extensions
     */
    private function autocompleter(AsCrud $orders, array $extra, array $extensions = []): RelationAutocompleter
    {
        $registry = $this->registry($orders, $this->people());
        $access = self::createStub(CrudAccess::class);
        $access->method('isGranted')->willReturn(true);
        $driver = $this->driver(true, $registry);
        $drivers = new CrudDrivers([$driver]);
        $records = new DefaultCrudRecords($drivers);
        $requests = new RequestStack();
        $requests->push(new Request(attributes: [
            CrudRouteLoader::AUTOCOMPLETE => true,
            Scope::ATTRIBUTE => $orders->scope,
        ]));
        $normalizer = new DoctrineEntityNormalizer($this->doctrine);
        $autocompleter = new RelationAutocompleter(
            $registry,
            $access,
            $this->doctrine,
            new CrudValues([$normalizer, new PlainValueNormalizer()]),
            $normalizer,
            new DoctrineRelations($registry, $this->doctrine, $this->scopes()),
            new DoctrineSearches(new WordsSearch(), new ServiceLocator([])),
            requests: $requests,
            formFields: new AutocompletedFormFields(
                $access,
                $drivers,
                $records,
                new RecordGuards(),
                new CrudLayouts($registry, $records),
                $extensions,
            ),
        );
        $autocompleter->setOptions([AutocompleteController::EXTRA_OPTIONS => $extra]);

        return $autocompleter;
    }

    /** An extension that takes the relation away from the one asking: "remove" or "disable". */
    private function extension(string $change): CrudFormExtension
    {
        return new readonly class ($change) implements CrudFormExtension {
            public function __construct(private string $change)
            {
            }

            public function resource(): string
            {
                return 'order';
            }

            public function buildForm(AsCrud $crud, FormBuilderInterface $builder, ?RecordView $record): void
            {
                if ($this->change === 'remove') {
                    $builder->remove('createdBy');
                } else {
                    $builder->get('createdBy')->setDisabled(true);
                }
            }
        };
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     *
     * @return FormInterface<mixed>
     */
    private function submitted(FormBuilderInterface $builder, string $person): FormInterface
    {
        $form = $builder->getForm();
        $form->submit(['createdBy' => $person], false);

        return $form;
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $values
     *
     * @return FormInterface<mixed>
     */
    private function sent(FormBuilderInterface $builder, array $values): FormInterface
    {
        $form = $builder->getForm();
        $form->submit($values, false);

        return $form;
    }

    /**
     * @param FormInterface<mixed> $field
     *
     * @return list<string>
     */
    private function choices(FormInterface $field): array
    {
        $choices = $field->createView()->vars['choices'] ?? [];

        return array_values(array_map(
            static fn (mixed $choice): string => $choice instanceof ChoiceView && is_string($choice->label)
                ? $choice->label
                : '',
            is_array($choices) ? $choices : [],
        ));
    }

    /**
     * @return list<mixed>
     */
    private function found(RelationAutocompleter $autocompleter): array
    {
        return array_column($autocompleter->fetchResults('', 1)->results, 'value');
    }
}
