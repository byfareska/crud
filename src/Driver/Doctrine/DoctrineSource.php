<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Driver\CrudSource;
use Byfareska\Crud\Resource\CrudOperation;
use InvalidArgumentException;

/**
 * Records that are entities of Doctrine ORM (driver "doctrine"): listed, filtered, ordered and paged by a query of
 * their own, saved and deleted by the entity manager. The forms are a form type of the application or, without one,
 * the fields the form component guesses from the mapping (relations become choices of the other entity).
 *
 *     #[AsCrud(name: 'product', path: '/products', icon: 'box-seam',
 *         source: new DoctrineSource(
 *             Product::class,
 *             search: ['name', 'sku'],
 *             filters: ['category', 'active'],
 *             sort: ['name'],
 *         ),
 *         columns: ['name', 'sku', 'category', 'price' => 'money', 'active'],
 *     )]
 *
 * The entity has to be made with no arguments (new Product()) unless the form type makes it (its "empty_data").
 * Its constraints (#[Assert\...]) are what the form checks.
 */
final readonly class DoctrineSource implements CrudSource
{
    public const string DRIVER = 'doctrine';

    /** The filter that takes a typed text, matched against the fields of $search. */
    public const string SEARCH_FILTER = 'query';

    /**
     * @param class-string $entity
     * @param class-string|DoctrineForm|null $form the add and edit forms: a form type, a DoctrineForm (a type with
     *     options, or guessed fields); null = the fields of $fields, their types guessed from the mapping
     * @param list<string> $fields what the guessed form has; empty = every mapped field and relation but the id, the
     *     version, columns the database fills and those whose name looks secret (AsCrud::looksSecret())
     * @param array<int|string, string|DoctrineFilter> $filters fields the list is filtered by, in the toolbar, each
     *     with a choice of how it compares (a date from - to, a number =, ≥, ≤..., a text contains...; see
     *     MappingFilterGuesser): 'status', or 'category' => EntityType::class to name the form type of the value, or
     *     'createdAt' => a FieldFilter to say how it compares, or 'priceFrom' => a DoctrineFilter of the
     *     application's own
     * @param list<string> $search fields a typed text is looked for in (the filter "query", also what the command
     *     palette finds records by)
     * @param array<int|string, string|DoctrineSort> $sortable what the headings of the list order by: 'name', or
     *     'category' => 'category.name' to order a column by a field of the related entity, or 'orders' => a
     *     DoctrineSort of the application's own (an expression, a subquery); empty = every column that is a mapped
     *     field
     * @param list<string> $sort the order of a list nobody sorted, "-" in front for descending, e.g. ['-createdAt']:
     *     names of "sortable" or fields; empty = by the id
     * @param list<CrudOperation> $operations what can be done; a read-only list: [CrudOperation::List,
     *     CrudOperation::Read]
     * @param string|null $manager the entity manager; null = the one of the entity
     * @param class-string|DoctrineForm|null $createForm the add form, in place of $form (a password only when a user is
     *     added); null = $form
     * @param class-string|DoctrineForm|null $updateForm the edit form, in place of $form (what is not changed once
     *     given left out); null = $form
     * @param class-string<DoctrineSearch>|null $searchWith how a typed text finds the entities in $search - a
     *     DoctrineSearch service (full-text, an exact SKU first); null = the DoctrineSearch of the application (by
     *     default WordsSearch: every word in one of the fields). Given alone (no $search), the list has its search
     *     field all the same: a search of its own (ElasticSearch) needs no fields of the entity
     * @param positive-int|null $countLimit the most records the list counts - a table of millions, whose COUNT would
     *     take long: past it the list says "more than N" and pages up to it (filters find the rest); null = it counts
     *     them all
     * @param array<string, string> $relations the resource whose scopes the entities of a relation are offered in, by
     *     the relation: ['product' => 'archived_products'] - for an entity of several resources (active and archived
     *     products) the filter of the relation and ?with[product]= reach those of that one; a relation not named
     *     takes the first resource of its entity, of the same panel first
     * @param class-string<DoctrineCount>|null $countWith how many records the list has, in place of the COUNT of its
     *     query - a DoctrineCount service (an estimate of the database, a count kept in a cache); null = COUNT, or
     *     up to $countLimit
     * @param bool $lock a record read in a transaction - of a delete, of a change (RecordChanges) - is locked for it
     *     (SELECT ... FOR UPDATE): what its guards go by ("not a paid invoice") cannot change until it is done
     */
    public function __construct(
        public string $entity,
        public string|DoctrineForm|null $form = null,
        public array $fields = [],
        public array $filters = [],
        public array $search = [],
        public array $sortable = [],
        public array $sort = [],
        public array $operations = [
            CrudOperation::List,
            CrudOperation::Read,
            CrudOperation::Create,
            CrudOperation::Update,
            CrudOperation::Delete,
        ],
        public ?string $manager = null,
        public string|DoctrineForm|null $createForm = null,
        public string|DoctrineForm|null $updateForm = null,
        public ?string $searchWith = null,
        public ?int $countLimit = null,
        public array $relations = [],
        public bool $lock = false,
        public ?string $countWith = null,
    ) {
        if ($countLimit !== null && $countLimit < 1) {
            throw new InvalidArgumentException('"countLimit" is the most records the list counts: 1 or more.');
        }
        if ($form instanceof DoctrineForm && $fields !== []) {
            throw new InvalidArgumentException('A DoctrineForm has its fields itself: "fields" go into it.');
        }
        foreach ($sortable as $key => $sort) {
            if (!is_string($sort) && (!is_string($key) || !$sort instanceof DoctrineSort)) {
                throw new InvalidArgumentException(sprintf(
                    'What a heading orders by is a field, or a %s named by the column ("orders" => new ...).',
                    DoctrineSort::class,
                ));
            }
        }
        foreach ($operations as $operation) {
            if (!$operation instanceof CrudOperation) {
                throw new InvalidArgumentException(sprintf(
                    '%s is no operation, only a case of %s.',
                    get_debug_type($operation),
                    CrudOperation::class,
                ));
            }
        }
    }

    /**
     * The same source with some of its arguments changed, for the request being answered - what a
     * CrudDefinitionExtension gives AsCrud::with(source: ...): a filter or a sorting only for a role, the entity
     * manager of a tenant, another count limit. Not the entity.
     */
    public function with(mixed ...$changes): self
    {
        if (array_key_exists('entity', $changes) || array_is_list($changes) && $changes !== []) {
            throw new InvalidArgumentException('Only named arguments of the source change, and not its entity.');
        }

        // @phpstan-ignore argument.type (named arguments of the constructor, which checks them)
        return new self(...[
            'entity' => $this->entity,
            'form' => $this->form,
            'fields' => $this->fields,
            'filters' => $this->filters,
            'search' => $this->search,
            'sortable' => $this->sortable,
            'sort' => $this->sort,
            'operations' => $this->operations,
            'manager' => $this->manager,
            'createForm' => $this->createForm,
            'updateForm' => $this->updateForm,
            'searchWith' => $this->searchWith,
            'countLimit' => $this->countLimit,
            'relations' => $this->relations,
            'lock' => $this->lock,
            'countWith' => $this->countWith,
            ...$changes,
        ]);
    }

    /**
     * The add form ($creating) or the edit form: createForm / updateForm, else form, else the guessed fields of
     * $fields.
     */
    public function formOf(bool $creating): DoctrineForm
    {
        $form = ($creating ? $this->createForm : $this->updateForm) ?? $this->form;

        return match (true) {
            $form instanceof DoctrineForm => $form,
            is_string($form) => new DoctrineForm($form),
            default => new DoctrineForm(fields: $this->fields),
        };
    }

    public function driver(): string
    {
        return self::DRIVER;
    }

    public function supports(CrudOperation $operation): bool
    {
        return in_array($operation, $this->operations, true);
    }

    public function searchField(): ?string
    {
        return $this->searches() ? self::SEARCH_FILTER : null;
    }

    /** Whether the list is searched by a typed text: fields to look in, or a search of its own. */
    public function searches(): bool
    {
        return $this->search !== [] || $this->searchWith !== null;
    }

    /**
     * The filters by the name of their field: a form type, a DoctrineFilter, or null for one guessed from the mapping.
     *
     * @return array<string, string|DoctrineFilter|null>
     */
    public function filterTypes(): array
    {
        $types = [];
        foreach ($this->filters as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $types[$value] = null;
            } elseif (is_string($key)) {
                $types[$key] = $value;
            }
        }

        return $types;
    }

    /**
     * What the headings order by: the name of the sort (a column) and the field it orders by, or a DoctrineSort.
     *
     * @return array<string, string|DoctrineSort>
     */
    public function sorts(): array
    {
        $sorts = [];
        foreach ($this->sortable as $key => $sort) {
            if (is_string($key)) {
                $sorts[$key] = $sort;
            } elseif (is_string($sort)) {
                $sorts[$sort] = $sort;
            }
        }

        return $sorts;
    }
}
