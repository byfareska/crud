<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CopiesRecords;
use Byfareska\Crud\Driver\CrudDriver;
use Byfareska\Crud\Driver\ListsIds;
use Byfareska\Crud\Driver\PrefillsForm;
use Byfareska\Crud\Filter\Compared;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\PageSizes;
use Byfareska\Crud\UseCase\ListPage;
use Byfareska\Crud\UseCase\Page;
use Byfareska\Crud\UseCase\Sort;
use Byfareska\Crud\Value\CrudValues;
use Byfareska\Crud\Value\TypedValue;
use DateTimeInterface;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Tools\Pagination\LimitSubqueryOutputWalker;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface as PropertyAccessExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;
use Throwable;
use TypeError;
use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;

/**
 * The records of a {@see DoctrineSource}: entities, read by a query of their own and written by the entity manager.
 *
 * - The list form has the filter "query" (when the source names fields to search in), a field per filter of the
 *   source, "sort", "page" and "perPage". A filter is a {@see DoctrineFilter} made by its handler: given in "filters",
 *   or, for a field named there, what {@see DoctrineFilterGuesser} makes of it - a {@see FieldFilter} that says how
 *   it compares (a date from - to, a number =, ≥, ≤..., a text contains...; see MappingFilterGuesser).
 * - The {@see DoctrineScope}s of the resource narrow the list and every record found by its id.
 * - A heading orders by its field, by the path the source maps it to ("category" => "category.name"), or by a
 *   {@see DoctrineSort} of the application's own, which its {@see DoctrineSortHandler} adds to the query.
 * - The {@see DoctrineListExtension}s of the resource change the query last; then it is ordered by the id, so what is
 *   equal by all of that keeps one order from page to page.
 * - A query joining what can give an entity more than one row (a collection) is paged by distinct entities.
 * - A typed text is split into words; every word has to be in one of the searched fields (in part, any case).
 * - An item of the list and a record are the mapped fields and relations by name, the values made printable by the
 *   ValueNormalizers (CrudValues): a date as ISO 8601, an enum as its value, another entity as its text
 *   (__toString()) or its id, a collection as a list of those - or what one of the application's own makes of it.
 */
final readonly class DoctrineDriver implements CrudDriver, ListsIds, PrefillsForm, CopiesRecords
{
    /**
     * The field of the edit form of a versioned entity (#[ORM\Version]) carrying the version it was opened with: a
     * live form is made anew from the entity in every request, so only the form remembers what the user saw.
     */
    public const string VERSION_FIELD = '_version';

    /** Columns of a day, no time: printed as such (TypedValue "day"). */
    private const array DAY_TYPES = ['date', 'date_immutable'];

    /**
     * @param iterable<DoctrineListExtension> $extensions
     * @param iterable<DoctrineItems> $itemExtensions
     */
    public function __construct(
        private ManagerRegistry $doctrine,
        private FormFactoryInterface $formFactory,
        private PropertyAccessorInterface $propertyAccessor,
        private CrudValues $values,
        private DoctrineEntityNormalizer $entities,
        private DoctrineFilterGuesser $filterGuesser,
        private DoctrineScopes $scopes,
        #[AutowireLocator(DoctrineFilterHandler::TAG)]
        private ContainerInterface $filterHandlers,
        #[AutowireLocator(DoctrineSortHandler::TAG)]
        private ContainerInterface $sortHandlers,
        private DoctrineSearches $searches,
        private PageSizes $pageSizes,
        private DoctrineRelations $relations,
        private DoctrineEntityFactory $factory,
        private DoctrinePersister $persister,
        private DoctrineExceptionMapper $exceptions,
        private DoctrineManagers $managers,
        #[AutowireIterator(DoctrineListExtension::TAG)]
        private iterable $extensions = [],
        private DoctrineIdCodec $ids = new DefaultDoctrineIdCodec(),
        #[AutowireIterator(DoctrineItems::TAG)]
        private iterable $itemExtensions = [],
        #[AutowireLocator(DoctrineCount::TAG)]
        private ?ContainerInterface $counters = null,
        private ?DoctrineRecordLabels $labels = null,
        private ?RelationFormField $relationFields = null,
    ) {
    }

    public function name(): string
    {
        return DoctrineSource::DRIVER;
    }

    public function listForm(AsCrud $crud): FormInterface
    {
        $source = $this->source($crud);
        $metadata = $this->metadata($source);
        $builder = $this->formFactory->createNamedBuilder('filters', FormType::class, null, [
            'csrf_protection' => false,
            'method' => 'GET',
        ]);

        if ($source->searches()) {
            $builder->add(DoctrineSource::SEARCH_FILTER, SearchType::class, [
                'label' => 'crud.filter.query',
                'required' => false,
                'empty_data' => '',
                'constraints' => [new Length(max: 200)],
            ]);
        }

        foreach ($this->filters($crud, $source, $metadata) as $name => $filter) {
            $options = ['label' => $crud->key(sprintf('field.%s.name', $name)), 'required' => false];
            $builder->add($this->filterHandler($filter)->field($filter, $name, $options));
        }

        // Every sortable field each way, and what the order of a list nobody sorted names.
        $default = $this->defaultSort($source, $metadata);
        $sorts = [];
        $sortable = [
            ...array_keys($this->sorts($crud, $source, $metadata)),
            ...array_map(static fn (string $sort): string => ltrim($sort, '-'), $default),
        ];
        foreach ($sortable as $field) {
            $sorts[$field] = $field;
            $sorts['-' . $field] = '-' . $field;
        }
        $builder->add('sort', ChoiceType::class, [
            'choices' => $sorts,
            'multiple' => true,
            'required' => false,
            'empty_data' => $default,
        ]);
        $builder->add('page', IntegerType::class, [
            'required' => false,
            'empty_data' => '1',
            'constraints' => [new Positive()],
        ]);
        // As many as the largest size the resource offers - not more by an address.
        $builder->add('perPage', IntegerType::class, [
            'required' => false,
            'empty_data' => (string) $this->pageSizes->defaultOf($crud),
            'constraints' => [new Range(min: 1, max: $this->maxPerPage($crud))],
        ]);
        // What an item carries besides the columns: what the actions of a record need (RecordAction::fields()).
        $builder->add('fields', CollectionType::class, [
            'entry_type' => TextType::class,
            'allow_add' => true,
            'required' => false,
        ]);

        return $builder->getForm();
    }

    public function list(AsCrud $crud, FormInterface $form): Page
    {
        $source = $this->source($crud);
        $metadata = $this->metadata($source);
        $query = $this->listQuery($crud, $source, $metadata, $form);
        $builder = $query->query;

        $page = max(1, $this->int($form->has('page') ? $form->get('page')->getData() : 1));
        $perPage = $form->has('perPage')
            ? $this->int($form->get('perPage')->getData())
            : $this->pageSizes->defaultOf($crud);
        $perPage = max(1, min($this->maxPerPage($crud), $perPage));

        /** @var Paginator<object> $paginator the query selects entities */
        $paginator = new Paginator($builder->getQuery(), fetchJoinCollection: $query->joinsMany());
        [$total, $more] = match (true) {
            $source->countWith !== null => $this->counter($source)->count($query),
            $source->countLimit !== null => $this->countUpTo($builder, $source->countLimit),
            default => [count($paginator), false],
        };
        $page = min($page, max(1, (int) ceil($total / $perPage)));
        $paginator->getQuery()->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        $asked = $form->has('fields') ? $form->get('fields')->getData() : [];
        $fields = [
            ...array_column($crud->columns, 'field'),
            ...$crud->fields,
            ...$crud->titleFields(),
            ...(is_array($asked) ? array_filter($asked, is_string(...)) : []),
        ];
        $fields = array_values(array_unique($fields));
        $items = [];
        $entities = [];
        foreach ($paginator as $row) {
            // An entity, or - when a DoctrineListExtension selects more (a count, a sum) - the entity and those values.
            [$entity, $selected] = $this->split($row);
            $item = $this->item($metadata, $entity, $fields);
            foreach ($selected as $alias => $value) {
                $item[$alias] = $this->values->normalize($value);
            }
            $items[] = $item;
            $entities[] = $entity;
        }

        return new ListPage($this->extendItems($crud, $entities, $items), $total, $page, $perPage, $more);
    }

    /**
     * The DoctrineCount the source names.
     *
     * @throws LogicException it is no service
     */
    private function counter(DoctrineSource $source): DoctrineCount
    {
        $id = (string) $source->countWith;
        $counter = $this->counters?->has($id) === true ? $this->counters->get($id) : null;

        return $counter instanceof DoctrineCount ? $counter : throw new LogicException(sprintf(
            'The list of %s is counted by %s, which is no service tagged "%s" - is it autoconfigured?',
            $source->entity,
            $id,
            DoctrineCount::TAG,
        ));
    }

    /**
     * The records of the list counted up to a limit: the ids of one more than it, unordered - the database stops
     * looking once it has them.
     *
     * @return array{int, bool} how many (at most the limit), and whether there are more
     */
    private function countUpTo(QueryBuilder $builder, int $limit): array
    {
        $counting = clone $builder;
        $counting->resetDQLPart('orderBy');
        $query = $counting->getQuery()->setFirstResult(0)->setMaxResults($limit + 1);
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LimitSubqueryOutputWalker::class);
        $found = count($query->getScalarResult());

        return [min($found, $limit), $found > $limit];
    }

    /**
     * Every id the list form matches, by one query of the ids alone (DISTINCT, in the order of the list) - what
     * Doctrine's paginator asks first. Null for an entity of an id of several fields.
     */
    public function ids(AsCrud $crud, FormInterface $form): ?iterable
    {
        $source = $this->source($crud);
        $metadata = $this->metadata($source);
        $identifier = $metadata->getIdentifierFieldNames();
        if (count($identifier) !== 1 || !$metadata->hasField($identifier[0])) {
            return null;
        }

        $query = $this->listQuery($crud, $source, $metadata, $form)->query->getQuery();
        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LimitSubqueryOutputWalker::class);
        $type = Type::getType($metadata->getTypeOfField($identifier[0]) ?? 'integer');
        $platform = $this->manager($source)->getConnection()->getDatabasePlatform();

        // The ids alone, as scalars (no entity is made): toIterable() is not allowed with the fetch joins of a list.
        $rows = $query->getScalarResult();

        return (function () use ($rows, $type, $platform): \Generator {
            foreach ($rows as $row) {
                if (!is_array($row) || $row === []) {
                    continue;
                }
                $id = $this->values->normalize($type->convertToPHPValue(current($row), $platform));
                if (is_scalar($id)) {
                    yield (string) $id;
                }
            }
        })();
    }

    /**
     * The query of the list the form asks for: the scopes, the search, the filters, the order, the extensions, the id
     * last.
     *
     * @param ClassMetadata<object> $metadata
     * @param FormInterface<mixed> $form
     */
    private function listQuery(
        AsCrud $crud,
        DoctrineSource $source,
        ClassMetadata $metadata,
        FormInterface $form,
    ): DoctrineListQuery {
        $builder = $this->manager($source)->createQueryBuilder()->select('e')->from($source->entity, 'e');
        $query = new DoctrineListQuery($crud, $builder, $metadata, $form);

        $this->scopes->apply($query);
        $this->search($query, $source);
        $this->filter($query, $source);
        $this->order($query, $source);
        foreach ($this->extensions as $extension) {
            if (in_array($extension->resource(), [$crud->name, DoctrineListExtension::EVERY], true)) {
                $extension->apply($query);
            }
        }
        $this->orderById($query);

        return $query;
    }

    public function read(AsCrud $crud, string $id): array
    {
        $source = $this->source($crud);
        $metadata = $this->metadata($source);
        $entity = $this->find($crud, $source, $metadata, $id) ?? throw CrudFailure::notFound();

        // Every mapped field and relation of its own, then what the details name besides (a getter of the entity, the
        // inverse side of a relation). Not the inverse sides by themselves: the products of a category are theirs,
        // and reading every one of them for each guard and delete would read the whole table.
        $fields = [
            ...$metadata->getFieldNames(),
            ...array_filter(
                $metadata->getAssociationNames(),
                static fn (string $relation): bool => !$metadata->isAssociationInverseSide($relation)
                    || !$metadata->isCollectionValuedAssociation($relation),
            ),
            ...array_column($crud->details, 'field'),
        ];

        $item = $this->item($metadata, $entity, array_values(array_unique($fields)));

        return $this->extendItems($crud, [$entity], [$item])[0] ?? $item;
    }

    /**
     * A row of the list: the entity, and what else its query selects by alias (not HIDDEN).
     *
     * @return array{object, array<string, mixed>}
     */
    private function split(mixed $row): array
    {
        if (is_object($row)) {
            return [$row, []];
        }
        $entity = null;
        $selected = [];
        foreach (is_array($row) ? $row : [] as $key => $value) {
            if ($entity === null && is_object($value) && is_int($key)) {
                $entity = $value;
            } elseif (is_string($key)) {
                $selected[$key] = $value;
            }
        }

        return [$entity ?? throw new LogicException('A row of the list has no entity.'), $selected];
    }

    /**
     * What the DoctrineItems of the resource add to its items, a page at once.
     *
     * @param list<object> $entities
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function extendItems(AsCrud $crud, array $entities, array $items): array
    {
        foreach ($this->itemExtensions as $extension) {
            if (in_array($extension->resource(), [$crud->name, DoctrineItems::EVERY], true)) {
                $items = array_values($extension->extend($crud, $entities, $items));
            }
        }

        return $items;
    }

    public function delete(AsCrud $crud, string $id): void
    {
        $source = $this->source($crud);
        $metadata = $this->metadata($source);
        $entity = $this->find($crud, $source, $metadata, $id) ?? throw CrudFailure::notFound();
        $manager = $this->manager($source);

        try {
            $this->persister->delete($crud, $entity, $manager);
        } catch (Throwable $exception) {
            throw $this->failure($crud, $source, CrudOperation::Delete, $exception);
        }
    }

    public function form(AsCrud $crud, ?string $id, array $with = []): FormBuilderInterface
    {
        $source = $this->source($crud);
        $metadata = $this->metadata($source);
        $entity = $id === null
            ? $this->factory->create($crud, $source)
            : ($this->find($crud, $source, $metadata, $id) ?? throw CrudFailure::notFound());

        $form = $source->formOf($id === null);
        // The live component endpoint has its own protection against cross-site requests.
        if ($form->type !== null) {
            $builder = $this->formFactory->createBuilder(
                $form->type,
                $entity,
                ['csrf_protection' => false] + $form->options,
            );
            $this->prefillEntity($crud, $metadata, $builder, $entity, $with);
            $this->carryVersion($source, $metadata, $builder, $entity, $id);

            return $builder;
        }

        $fields = $form->fields !== [] ? $form->fields : $this->formFields($source, $metadata, $id === null);
        $options = $form->options;
        if ($entity === null && !array_key_exists('empty_data', $options)) {
            // A constructor with arguments: the entity is made of the fields named as its parameters.
            $options['empty_data'] = $this->constructorData($crud, $source, $fields);
        }

        $builder = $this->formFactory->createNamedBuilder(
            $crud->name,
            FormType::class,
            $entity,
            ['csrf_protection' => false] + $options + ['data_class' => $source->entity, 'label' => false],
        );
        foreach ($fields as $field) {
            // No type: guessed from the mapping (a relation is a choice of the other entity).
            $options = ['label' => $crud->key(sprintf('field.%s.name', $field))];
            if ($metadata->hasAssociation($field)) {
                // A choice of every related entity, or a field to search in with symfony/ux-autocomplete.
                $options += ($this->relationFields ?? new ChoiceRelationFormField($this->doctrine, $this->labels))
                    ->options($crud, $field, $metadata->getAssociationTargetClass($field), $entity, $id);
            }
            // A text field left empty is null; a column that cannot be null takes it as "" (its constraints judge it).
            if (
                $metadata->hasField($field)
                && !$metadata->isNullable($field)
                && in_array($metadata->getTypeOfField($field), ['string', 'text', 'ascii_string'], true)
            ) {
                $options['empty_data'] = '';
            }
            $builder->add($field, null, $options);
        }
        $this->prefillEntity($crud, $metadata, $builder, $entity, $with);
        $this->carryVersion($source, $metadata, $builder, $entity, $id);

        return $builder;
    }

    public function prefill(AsCrud $crud, FormBuilderInterface $builder, array $with): void
    {
        $entity = $builder->getData();
        $this->prefillEntity(
            $crud,
            $this->metadata($this->source($crud)),
            $builder,
            is_object($entity) ? $entity : null,
            $with,
        );
    }

    public function copy(AsCrud $crud, string $id, FormBuilderInterface $builder, array $fields): void
    {
        $source = $this->source($crud);
        $metadata = $this->metadata($source);
        $entity = $builder->getData();
        $copied = $this->find($crud, $source, $metadata, $id) ?? throw CrudFailure::notFound();
        if (!is_object($entity)) {
            return;
        }

        foreach ($fields as $field) {
            // Of the entity itself: not its id, nor its version; a relation of many (tags) as a new collection of the
            // same entities - the copy has them too, the original keeps its own. The inverse side (the lines of an
            // order) is RecordCopying's: those would be moved, not copied.
            $copies = $metadata->hasField($field)
                ? !$metadata->isIdentifier($field) && (!$metadata->isVersioned || $metadata->versionField !== $field)
                : $metadata->hasAssociation($field) && !$metadata->isAssociationInverseSide($field);
            if (
                !$copies
                || !$this->propertyAccessor->isReadable($copied, $field)
                || !$this->propertyAccessor->isWritable($entity, $field)
            ) {
                continue;
            }
            $value = $this->propertyAccessor->getValue($copied, $field);
            if ($value instanceof Collection) {
                $value = new ArrayCollection($value->toArray());
            }

            try {
                $this->propertyAccessor->setValue($entity, $field, $value);
            } catch (PropertyAccessExceptionInterface) {
                // Not to be set this way (an adder only): the copy goes without it.
            }
        }
    }

    public function save(AsCrud $crud, FormInterface $form, ?string $id): ?string
    {
        $source = $this->source($crud);
        $entity = $form->getData();
        if (!$entity instanceof $source->entity) {
            throw new LogicException(sprintf('The form of "%s" did not give a "%s".', $crud->name, $source->entity));
        }
        $manager = $this->manager($source);
        $this->assertSameVersion($source, $form, $entity);

        try {
            $this->persister->save($crud, $entity, $id === null, $manager);
        } catch (Throwable $exception) {
            $operation = $id === null ? CrudOperation::Create : CrudOperation::Update;

            throw $this->failure($crud, $source, $operation, $exception);
        }

        return $this->idOf($this->metadata($source), $entity);
    }

    /**
     * The filters of the source by the name of their field: those it gives, and what the guesser makes of the fields
     * it names. A FieldFilter given without a type takes the one guessed for its field.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, DoctrineFilter>
     */
    private function filters(AsCrud $crud, DoctrineSource $source, ClassMetadata $metadata): array
    {
        $filters = [];
        foreach ($source->filterTypes() as $name => $type) {
            $filters[$name] = match (true) {
                $type instanceof FieldFilter && $type->type === null => $this->typed($crud, $metadata, $type),
                $type instanceof DoctrineFilter => $type,
                default => $this->filterGuesser->guess($crud, $metadata, $name, $type),
            };
            // A field searched as one types (RelationFilterField) is told the filter it is of: two filters may end
            // with the same relation ("parent" and "category.parent").
            $filter = $filters[$name];
            $extra = $filter instanceof FieldFilter ? $filter->options['extra_options'] ?? null : null;
            if ($filter instanceof FieldFilter && is_array($extra)) {
                $filters[$name] = new FieldFilter(
                    $filter->field,
                    $filter->comparisons,
                    $filter->type,
                    ['extra_options' => [RelationFilterField::FILTER => $name] + $extra] + $filter->options,
                );
            }
        }

        return $filters;
    }

    /**
     * A FieldFilter with the form type (and its options) guessed for its field - of a related entity too; the options
     * it was given are kept over the guessed ones.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function typed(AsCrud $crud, ClassMetadata $metadata, FieldFilter $filter): FieldFilter
    {
        $field = $filter->field;
        // Through relations, however far ("customer.country"); an embedded object's field is the entity's own.
        while (str_contains($field, '.') && !$metadata->hasField($field)) {
            [$relation, $field] = explode('.', $field, 2);
            if (!$metadata->hasAssociation($relation)) {
                return $filter;
            }
            $target = $metadata->getAssociationTargetClass($relation);
            // In the database of the resource (a tenant's), as its records are.
            $metadata = $this->managers->of($crud, $target)->getClassMetadata($target);
        }
        $guess = $this->filterGuesser->guess($crud, $metadata, $field, null);

        if (!$guess instanceof FieldFilter) {
            return $filter;
        }
        // The options given win over those guessed: a query_builder, a choice_label of the application's - and its
        // "extra_options" (per_page of a field searched as one types) go next to the guessed ones, not instead.
        $options = $filter->options + $guess->options;
        $given = $filter->options['extra_options'] ?? null;
        $guessed = $guess->options['extra_options'] ?? null;
        if (is_array($given) && is_array($guessed)) {
            $options['extra_options'] = $given + $guessed;
        }

        return new FieldFilter($filter->field, $filter->comparisons, $guess->type, $options);
    }

    private function search(DoctrineListQuery $query, DoctrineSource $source): void
    {
        if (!$source->searches() || !$query->form->has(DoctrineSource::SEARCH_FILTER)) {
            return;
        }
        $text = $query->form->get(DoctrineSource::SEARCH_FILTER)->getData();
        $text = is_string($text) ? trim($text) : '';
        if ($text !== '') {
            $this->searches->of($source)->apply($query, $source->search, $text);
        }
    }

    private function filter(DoctrineListQuery $query, DoctrineSource $source): void
    {
        foreach ($this->filters($query->crud, $source, $query->metadata) as $name => $filter) {
            if (!$query->form->has($name)) {
                continue;
            }
            $value = $query->form->get($name)->getData();
            if (!Compared::isEmpty($value)) {
                $this->filterHandler($filter)->apply($filter, $query, $name, $value);
            }
        }
    }

    /**
     * Orders by the sorts asked, each by what the source says it orders by: a DoctrineSort, a field.
     */
    private function order(DoctrineListQuery $query, DoctrineSource $source): void
    {
        $sorts = $this->sorts($query->crud, $source, $query->metadata);
        $asked = $query->form->has('sort') ? $query->form->get('sort')->getData() : [];
        foreach (Sort::parseAll(is_array($asked) ? $asked : []) as $sort) {
            // The order of a list nobody sorted can name a field that is not among the sortable ones.
            $by = $sorts[$sort->field] ?? $this->fieldSort($query->metadata, $sort->field);
            if ($by !== null) {
                $this->sortHandler($by)->apply($by, $query, $sort->isDescending());
            }
        }
    }

    /**
     * Orders by the id what is ordered by nothing else: the same order from page to page.
     */
    private function orderById(DoctrineQuery $query): void
    {
        /** @var list<OrderBy> $orders */
        $orders = $query->query->getDQLPart('orderBy');
        $ordered = [];
        foreach ($orders as $order) {
            foreach ($order->getParts() as $part) {
                $ordered[] = explode(' ', $part, 2)[0];
            }
        }
        foreach ($query->metadata->getIdentifierFieldNames() as $id) {
            if ($query->metadata->hasField($id) && !in_array('e.' . $id, $ordered, true)) {
                $query->query->addOrderBy('e.' . $id, DoctrineOrder::of(false));
            }
        }
    }

    /**
     * What the headings can order by, by the name of the sort: the source says (a path through a relation, a
     * DoctrineSort), else every column that is a mapped field.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, DoctrineSort>
     */
    private function sorts(AsCrud $crud, DoctrineSource $source, ClassMetadata $metadata): array
    {
        $sorts = [];
        if ($source->sortable === []) {
            foreach (array_column($crud->columns, 'field') as $field) {
                if ($metadata->hasField($field)) {
                    $sorts[$field] = new FieldSort($field);
                }
            }

            return $sorts;
        }

        foreach ($source->sorts() as $name => $sort) {
            $sort = is_string($sort) ? $this->fieldSort($metadata, $sort) : $sort;
            if ($sort !== null) {
                $sorts[$name] = $sort;
            }
        }

        return $sorts;
    }

    /**
     * The order by a field of the entity, or of a related one ("category.name"); null for what it has not.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function fieldSort(ClassMetadata $metadata, string $path): ?FieldSort
    {
        // A field, also of an embedded object ("address.city"), or one through a relation.
        $known = $metadata->hasField($path)
            || str_contains($path, '.') && $metadata->hasAssociation(explode('.', $path, 2)[0]);

        return $known ? new FieldSort($path) : null;
    }

    /**
     * @throws LogicException the handler the sort names is no service
     */
    private function sortHandler(DoctrineSort $sort): DoctrineSortHandler
    {
        $id = $sort->handler();
        if (!$this->sortHandlers->has($id)) {
            throw new LogicException(sprintf(
                'The sort %s is made by %s, which is no service tagged "%s" - is it autoconfigured?',
                $sort::class,
                $id,
                DoctrineSortHandler::TAG,
            ));
        }
        $handler = $this->sortHandlers->get($id);
        assert($handler instanceof DoctrineSortHandler);

        return $handler;
    }

    /**
     * @throws LogicException the handler the filter names is no service
     */
    private function filterHandler(DoctrineFilter $filter): DoctrineFilterHandler
    {
        $id = $filter->handler();
        if (!$this->filterHandlers->has($id)) {
            throw new LogicException(sprintf(
                'The filter %s is made by %s, which is no service tagged "%s" - is it autoconfigured?',
                $filter::class,
                $id,
                DoctrineFilterHandler::TAG,
            ));
        }
        $handler = $this->filterHandlers->get($id);
        assert($handler instanceof DoctrineFilterHandler);

        return $handler;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return list<string>
     */
    private function defaultSort(DoctrineSource $source, ClassMetadata $metadata): array
    {
        if ($source->sort !== []) {
            return $source->sort;
        }

        return array_values(array_filter($metadata->getIdentifierFieldNames(), $metadata->hasField(...)));
    }

    /**
     * The names of the parameters of the constructor of the entity, when it has required ones; else none.
     *
     * @return list<string>
     */
    private function constructorParameters(DoctrineSource $source): array
    {
        $constructor = new ReflectionClass($source->entity)->getConstructor();
        if (!$constructor instanceof ReflectionMethod || $constructor->getNumberOfRequiredParameters() === 0) {
            return [];
        }

        return array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->name,
            $constructor->getParameters(),
        );
    }

    /**
     * The "empty_data" of the add form of an entity whose constructor takes arguments: the constructor given the data
     * of the fields named as its parameters (the defaults of those the form has not). A required one the form cannot
     * give is a mistake of the declaration, told when the form is made; one left empty is null, and the constraints
     * of the field (NotBlank) or of the entity say what is wrong - not a TypeError.
     *
     * @param list<string> $fields
     */
    private function constructorData(AsCrud $crud, DoctrineSource $source, array $fields): Closure
    {
        $class = new ReflectionClass($source->entity);
        $constructor = $class->getConstructor();
        if (!$constructor instanceof ReflectionMethod) {
            return $class->newInstance(...);
        }
        foreach ($constructor->getParameters() as $parameter) {
            if (!$parameter->isOptional() && !in_array($parameter->name, $fields, true)) {
                throw new LogicException(sprintf(
                    'The add form of "%s" is guessed, and the constructor of %s takes "$%s", which no field of the '
                    . 'form gives: add it to the fields (DoctrineForm fields: [...]), name a form type whose '
                    . '"empty_data" makes the entity, or alias DoctrineEntityFactory.',
                    $crud->name,
                    $source->entity,
                    $parameter->name,
                ));
            }
        }

        return static function (FormInterface $form) use ($class, $constructor): ?object {
            $arguments = [];
            foreach ($constructor->getParameters() as $parameter) {
                if ($form->has($parameter->name)) {
                    $value = $form->get($parameter->name)->getData();
                    if ($value === null && !$parameter->allowsNull()) {
                        // Not to be made: the form shows why (its fields' constraints, "required").
                        return null;
                    }
                    $arguments[] = $value;
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                } else {
                    return null;
                }
            }

            try {
                return $class->newInstanceArgs($arguments);
            } catch (TypeError) {
                return null;
            }
        };
    }

    /**
     * An instance of the entity to ask what can be written - not made by its constructor.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function blank(ClassMetadata $metadata): object
    {
        return $metadata->getReflectionClass()->newInstanceWithoutConstructor();
    }

    /**
     * What the guessed form has: every mapped field and relation but those nobody types in - the id, the version of
     * optimistic locking, a column the database fills (generated, or not to be inserted / updated by the form's
     * operation) - and those whose name looks secret (AsCrud::looksSecret(): a password hash, a token), which no
     * input should show. Name them in "fields" to have them all the same. The inverse sides of relations are left
     * out too.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return list<string>
     */
    private function formFields(DoctrineSource $source, ClassMetadata $metadata, bool $create): array
    {
        $constructor = $create ? $this->constructorParameters($source) : [];
        // A property with no way to write it (set in the constructor, no setter) is shown, not edited - unless the
        // constructor of a new entity takes it.
        $writable = fn (string $field): bool => in_array($field, $constructor, true)
            || $this->propertyAccessor->isWritable($this->blank($metadata), $field);
        $fields = [];
        foreach ($metadata->getFieldNames() as $field) {
            $mapping = $metadata->getFieldMapping($field);
            if (
                $metadata->isIdentifier($field)
                || ($metadata->isVersioned && $metadata->versionField === $field)
                || ($mapping->generated ?? ClassMetadata::GENERATED_NEVER) !== ClassMetadata::GENERATED_NEVER
                || ($create ? $mapping->notInsertable === true : $mapping->notUpdatable === true)
                || AsCrud::looksSecret($field)
                || !$writable($field)
            ) {
                continue;
            }
            $fields[] = $field;
        }
        foreach ($metadata->getAssociationNames() as $relation) {
            if (
                $metadata->isAssociationInverseSide($relation)
                || AsCrud::looksSecret($relation)
                || !$writable($relation)
            ) {
                continue;
            }
            $fields[] = $relation;
        }

        return $fields;
    }

    /**
     * A record by field, its values made printable.
     *
     * @param ClassMetadata<object> $metadata
     * @param list<string> $fields
     *
     * @return array<string, mixed>
     */
    private function item(ClassMetadata $metadata, object $entity, array $fields): array
    {
        $item = ['id' => $this->idOf($metadata, $entity)];
        foreach ($fields as $field) {
            if ($field === 'id') {
                continue;
            }
            if ($metadata->hasField($field) || $metadata->hasAssociation($field)) {
                $value = $metadata->getFieldValue($entity, $field);
            } elseif ($this->propertyAccessor->isReadable($entity, $field)) {
                $value = $this->propertyAccessor->getValue($entity, $field);
            } else {
                continue;
            }
            // A day is no moment: "2024-05-01", printed without a time (type_day).
            $item[$field] = $value instanceof DateTimeInterface
                && $metadata->hasField($field)
                && in_array($metadata->getTypeOfField($field), self::DAY_TYPES, true)
                    ? new TypedValue('day', $value->format('Y-m-d'))
                    : $this->values->normalize($value);
        }

        return $item;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function idOf(ClassMetadata $metadata, object $entity): ?string
    {
        return $this->entities->idOf($metadata, $entity, $this->values);
    }

    /**
     * The entity of the id, if the scopes of the resource leave it.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function find(AsCrud $crud, DoctrineSource $source, ClassMetadata $metadata, string $id): ?object
    {
        // What the id names (DoctrineIdCodec): its fields; nothing for an id of another kind.
        $criteria = $this->ids->decode($metadata, $id);
        if ($criteria === null) {
            return null;
        }

        // Locked for the transaction it is read in, when the source says so (a delete, a change asking its guards).
        $lock = $source->lock && $this->manager($source)->getConnection()->isTransactionActive()
            ? LockMode::PESSIMISTIC_WRITE
            : LockMode::NONE;

        if (!$this->scopes->narrow($crud)) {
            try {
                return $this->manager($source)->find($source->entity, $criteria, $lock);
            } catch (Throwable) {
                // Not an id of that kind (a broken uuid): nothing has it.
                return null;
            }
        }

        $builder = $this->manager($source)->createQueryBuilder()->select('e')->from($source->entity, 'e');
        $query = new DoctrineQuery($crud, $builder, $metadata);
        foreach ($criteria as $field => $value) {
            $parameter = $query->parameter('id');
            $type = $metadata->hasField($field) ? $metadata->getTypeOfField($field) : null;
            $builder->andWhere(sprintf('e.%s = :%s', $field, $parameter))->setParameter($parameter, $value, $type);
        }
        $this->scopes->apply($query);

        try {
            $read = $builder->getQuery();
            if ($lock !== LockMode::NONE) {
                $read->setLockMode($lock);
            }
            $entity = $read->getOneOrNullResult();
        } catch (ConversionException) {
            // Not an id of that kind (a broken uuid): nothing has it.
            return null;
        }

        return is_object($entity) ? $entity : null;
    }

    /**
     * The edit form of a versioned entity remembers the version it was opened with.
     *
     * @param ClassMetadata<object> $metadata
     * @param FormBuilderInterface<mixed> $builder
     */
    private function carryVersion(
        DoctrineSource $source,
        ClassMetadata $metadata,
        FormBuilderInterface $builder,
        ?object $entity,
        ?string $id,
    ): void {
        if ($id === null || $entity === null || !$metadata->isVersioned || $builder->has(self::VERSION_FIELD)) {
            return;
        }

        $builder->add(self::VERSION_FIELD, HiddenType::class, [
            'mapped' => false,
            'data' => $this->versionOf($source, $metadata, $entity),
        ]);
    }

    /**
     * Refuses to save an entity somebody else has changed since its form was opened (optimistic locking): its version
     * is no longer the one the form remembers. Doctrine checks the version once more as it writes, for a change made
     * in the meantime.
     *
     * @param FormInterface<mixed> $form
     *
     * @throws CrudFailure 409, crud.error.changed
     */
    private function assertSameVersion(DoctrineSource $source, FormInterface $form, object $entity): void
    {
        if (!$form->has(self::VERSION_FIELD)) {
            return;
        }
        $metadata = $this->metadata($source);
        $seen = $form->get(self::VERSION_FIELD)->getData();
        if (is_scalar($seen) && (string) $seen === $this->versionOf($source, $metadata, $entity)) {
            return;
        }

        throw new CrudFailure(
            409,
            sprintf('"%s" was changed since its form was opened.', $source->entity),
            null,
            null,
            'crud.error.changed',
        );
    }

    /**
     * The version of a versioned entity as text, as its database column has it.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function versionOf(DoctrineSource $source, ClassMetadata $metadata, object $entity): string
    {
        $field = $metadata->versionField ?? throw new LogicException(sprintf('"%s" has no version.', $source->entity));
        $value = Type::getType($metadata->getTypeOfField($field) ?? 'integer')->convertToDatabaseValue(
            $metadata->getFieldValue($entity, $field),
            $this->manager($source)->getConnection()->getDatabasePlatform(),
        );

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * What the address gives a new record (?with[category]=5): only for a field of its form - nothing the form keeps
     * from the user (an owner, a flag of approval) comes in this way - and a related entity only if the scopes of
     * its resource leave it (DoctrineRelations). A value the field cannot take is left out.
     *
     * @param ClassMetadata<object> $metadata
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, mixed> $with
     */
    private function prefillEntity(
        AsCrud $crud,
        ClassMetadata $metadata,
        FormBuilderInterface $builder,
        ?object $entity,
        array $with,
    ): void {
        if ($entity === null) {
            return;
        }

        foreach ($with as $field => $value) {
            $field = (string) $field;
            if (
                !is_scalar($value)
                || !$builder->has($field)
                || $builder->get($field)->getDisabled()
                || !$builder->get($field)->getMapped()
                || !$this->propertyAccessor->isWritable($entity, $field)
            ) {
                continue;
            }
            if ($metadata->hasAssociation($field)) {
                if ($metadata->isCollectionValuedAssociation($field) || is_bool($value) || is_float($value)) {
                    continue;
                }
                $value = $this->relations->find($crud, $metadata->getAssociationTargetClass($field), $value, $field);
            } elseif (!$metadata->hasField($field)) {
                continue;
            }
            if ($value === null) {
                continue;
            }

            try {
                $this->propertyAccessor->setValue($entity, $field, $value);
            } catch (PropertyAccessExceptionInterface) {
                // Not a value of that field (a text for a date): the form starts without it.
            }
        }
    }

    /**
     * The most records a page can have: the largest size the resource offers.
     */
    private function maxPerPage(AsCrud $crud): int
    {
        return max($this->pageSizes->of($crud));
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 1;
    }

    private function manager(DoctrineSource $source): EntityManagerInterface
    {
        $manager = $source->manager === null
            ? $this->doctrine->getManagerForClass($source->entity)
            : $this->doctrine->getManager($source->manager);

        return $manager instanceof EntityManagerInterface
            ? $manager
            : throw new LogicException(sprintf('"%s" is not an entity of Doctrine ORM.', $source->entity));
    }

    /**
     * What an error of a save or a delete is to show (DoctrineExceptionMapper), else the error itself. An entity
     * manager the error closed (a failed flush) is made anew for what comes next in the request.
     */
    private function failure(
        AsCrud $crud,
        DoctrineSource $source,
        CrudOperation $operation,
        Throwable $exception,
    ): Throwable {
        if (!$this->manager($source)->isOpen()) {
            $this->forget($source);
        }

        return $this->exceptions->map($crud, $operation, $exception) ?? $exception;
    }

    /**
     * A failed flush closes the entity manager: a new one for what comes next.
     */
    private function forget(DoctrineSource $source): void
    {
        $this->doctrine->resetManager($source->manager ?? $this->managerName($source));
    }

    /**
     * The name of the manager of the entity of a source that names none (the one its mapping is in), for
     * resetManager(): without a name that would be the default one, which may be another.
     */
    private function managerName(DoctrineSource $source): ?string
    {
        $manager = $this->doctrine->getManagerForClass($source->entity);
        foreach (array_keys($this->doctrine->getManagerNames()) as $name) {
            if ($this->doctrine->getManager($name) === $manager) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return ClassMetadata<object>
     */
    private function metadata(DoctrineSource $source): ClassMetadata
    {
        return $this->manager($source)->getClassMetadata($source->entity);
    }

    private function source(AsCrud $crud): DoctrineSource
    {
        return $crud->source instanceof DoctrineSource
            ? $crud->source
            : throw new LogicException(sprintf('CRUD resource "%s" is not one of entities.', $crud->name));
    }
}
