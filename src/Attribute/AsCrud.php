<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;
use Byfareska\Crud\Action\BulkAction;
use Byfareska\Crud\Action\RecordAction;
use Byfareska\Crud\Driver\CrudSource;
use Byfareska\Crud\Driver\Dto\DtoSource;
use Byfareska\Crud\Layout\CrudLayout;
use Byfareska\Crud\Layout\LayoutNode;
use Byfareska\Crud\Resource\AfterSave;
use Byfareska\Crud\Resource\Column;
use Byfareska\Crud\Resource\CrudListedIn;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Routing\PageParametersListener;
use Byfareska\Crud\UseCase\MapsToRequest;
use Byfareska\Crud\Value\ValueFormat;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Declares a section of the panel: a list page and, for the operations its source can do, the pages and actions to
 * show, add, edit and delete a record.
 *
 * Put it on a class - an empty one of its own (App\Crud\ProductCrud), or the entity itself. Routes:
 * crud_<name>_list (with the optional parameter "page": <path>/page/3), crud_<name>_new, crud_<name>_show,
 * crud_<name>_edit, imported by the "byfareska_crud" route loader of the resource's scope.
 *
 * Where the records are is said by "source" and read by its driver:
 * - new DoctrineSource(Product::class, ...) - an entity, listed, filtered and saved by Doctrine (driver "doctrine");
 * - list:, read:, create:, update:, delete: - DTOs naming their use cases (#[UseCase]), the shorthand of a
 *   {@see DtoSource} (driver "dto"); the same DTOs can be the input of an API or a console command;
 * - a CrudSource of the application's own, read by a CrudDriver of its own.
 *
 * Texts are translations (messages domain): crud.<name>.title, crud.<name>.new, crud.<name>.edit and
 * crud.<name>.field.<field>.name for the heading of a column or a detail. Everything else about a field goes
 * next to it - crud.<name>.field.<field>.help, crud.<name>.field.<field>.error.<what>.
 *
 * With "section" the list is a page of the panel's menu, under that heading (a translation key; "priority" places
 * the page, and so its section). With "role" the pages, the menu and the palette are only for somebody who has it -
 * and, role or not, only for those the panel of "scope" is for (byfareska_crud.scopes.<scope>.role).
 * "roles" narrows an operation further: `roles: ['create' => 'ROLE_MANAGER', 'delete' => 'ROLE_MANAGER']` - everybody
 * the resource is for lists and reads, only a manager adds and deletes. Both are asked with the resource as the
 * subject, so a voter of the application can decide by more than a role.
 *
 * "actions" adds what else can be done with every record ({@see RecordAction}): a link to a page ("sign in as this
 * user"), a Twig component, or a kind of action of the application's own. The built-in ones - DetailsAction,
 * EditAction, DeleteAction - are there for what the resource can do; one declared here takes their place, e.g. to be
 * a button on the row of the list rather than an item of its menu: `new EditAction(inline: true)`. Who may do what
 * with one record is said by RecordGuard services.
 *
 * A column prints its values with the blocks of the theme, or with a {@see ValueFormat} given in place of its type.
 *
 * Other parts of the application add fields to the edit form with {@see AsCrudFormSection}, parts under the details
 * with {@see AsCrudDetailsSection} and pages to every record with {@see AsCrudPage}; a controller of the application
 * gets a page about a record with {@see MapCrud}.
 *
 * "layout" arranges the add and edit forms, the details page and the preview drawer in tabs, columns, fieldsets and
 * rows (Layout\Tab, Column, Fieldset, Row) holding the fields by name - those of the form or the record, and the
 * sections; see {@see CrudLayout} for what may hold what. Each page takes the fields it has and drops the groups
 * left empty; a form field the layout does not name comes after it, a property of the record it does not name is
 * not shown. "formLayout" / "detailsLayout" replace it for the forms / the details. The headings are translations:
 * crud.<name>.layout.<group>.name, with an optional crud.<name>.layout.<group>.help under them.
 *
 * The command palette offers the operations named in "palette" (see CrudPaletteSource, CrudPaletteRecords): the
 * list and the page to add a record as entries, and, for "read", the records found by typing - which needs "search",
 * the filter of the list that takes the typed text (the source's own by default). Optional search terms:
 * crud.<name>.palette.keywords.
 *
 * The attribute may be put on one class several times: each is a resource of its own, with a name of its own (the
 * names are unique - routes, texts, sections and theme blocks go by it) and a route scope of its own.
 *
 * With "own" the resource is the record of the one signed in - their account, a profile: no list, no adding or
 * deleting, the details page at $path and the edit form at <path>/edit, with no id in the address (the id is
 * Actor::id()).
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsCrud
{
    /** Added to every class carrying the attribute (CrudBundle::build()). */
    public const string TAG = 'byfareska_crud.resource';

    /** The scope of a resource that names none. */
    public const string DEFAULT_SCOPE = 'panel';

    /** What a name of a resource is made of. */
    public const string NAME = '/^[a-z][a-z0-9_]*$/';

    /**
     * Names a resource cannot have: its texts are crud.<name>.*, and these are the bundle's own (crud.action.edit is
     * the "Edit" button of every list - a resource "action" would have its title there).
     */
    public const array RESERVED_NAMES = [
        'action', 'actions', 'all_matching_selected', 'clear_filters', 'clear_selection', 'confirm_bulk_delete',
        'confirm_delete', 'empty', 'error', 'filter', 'layout', 'palette', 'search', 'select', 'select_all_matching',
        'select_page', 'selected', 'sort_tip', 'toast', 'total_html', 'total_more_html', 'value',
    ];

    /**
     * The words of the name of a property that is kept secret - "passwordHash", "reset_token", "apiKey": the details
     * that show every property (no "details") leave it out. Named in "details" it is shown. Words, not parts of them:
     * "hashtag" and "passport" are shown.
     */
    public const array SECRET = [
        'password', 'passwd', 'passphrase', 'secret', 'secrets', 'token', 'tokens', 'salt', 'hash', 'credential',
        'credentials', 'otp', 'totp', 'apikey', 'api key', 'private key', 'pin code',
    ];

    /** What a parameter of its path is made of: {category}, {tenantId}. */
    public const string PARAMETER = '/^[a-z][A-Za-z0-9_]*$/';

    /**
     * What with() may change: everything but what the routes are made of (name, path, scope, own, listedIn,
     * idPattern) and who the resource is for (role). The source only for one of the same records and operations
     * (see with()).
     */
    public const array CHANGEABLE = [
        'source', 'icon', 'columns', 'details', 'theme', 'palette', 'search', 'title', 'section', 'priority', 'actions',
        'fields', 'formLayout', 'detailsLayout', 'listHead', 'listActions', 'preview', 'bulkActions', 'perPage',
        'filtersInView', 'defaultPerPage', 'listComponent', 'formComponent', 'hidden', 'afterSave', 'pageTemplates',
        'options',
    ];

    /** Where the records are, and which driver reads them. */
    public CrudSource $source;

    /** The filter of the list the palette types into; null = records are not found by typing. */
    public ?string $search;

    /** @var list<Column> */
    public array $columns;

    /** @var list<Column> */
    public array $details;

    /** @var list<LayoutNode|string> */
    public array $formLayout;

    /** @var list<LayoutNode|string> */
    public array $detailsLayout;

    /**
     * @param string $name lower-case letters, digits and "_"; part of the route names and of the translation keys
     * @param string $path of the list page, e.g. "/users"; of the details page of an "own" resource. Parameters of the
     *     application's own ("/categories/{category}/products": the products of one category, a DoctrineScope reading
     *     PageParameters) - then the resource is reached from a page that knows them, not from the menu nor the
     *     palette; none named like the bundle's (id, page...)
     * @param string $icon a Bootstrap Icons name
     * @param CrudSource|null $source where the records are - see DoctrineSource, DtoSource; null = a DtoSource of the
     *     five DTOs below
     * @param class-string<MapsToRequest>|null $list (dto) required, except for an "own" resource, which has none
     * @param class-string<MapsToRequest>|null $read (dto)
     * @param class-string<MapsToRequest>|null $create (dto)
     * @param class-string<MapsToRequest>|null $update (dto)
     * @param class-string<MapsToRequest>|null $delete (dto)
     * @param array<int|string, string|ValueFormat|Column> $columns fields of a list item, in order: 'name', or
     *     'email' => 'email' to also say what type of value it is, or 'stock' => a ValueFormat to print it with - see
     *     CrudValueRenderer; or a Column (what with() is given)
     * @param array<int|string, string|ValueFormat|Column> $details what the preview drawer and the details page show,
     *     like $columns but by property of the record; empty = every property, typed like the column of the same name
     * @param list<string> $fields what else the list asks of every item, for the blocks of the theme: the picture shown
     *     next to a name is no column
     * @param string|null $theme a Twig template with blocks that print the values of this resource, looked into
     *     before @CrudTheme/crud/values.html.twig
     * @param list<CrudOperation> $palette what the command palette offers, of List, Create and Read; an operation the
     *     resource does not have is left out
     * @param string|null $search the filter of the list matched against a typed text, e.g. "query"; null = the one of
     *     the source (CrudSource::searchField())
     * @param list<string> $title the fields a record is named by (on its details page, in the palette), a space between
     *     them, e.g. first and last name; empty = the first column
     * @param string|null $section translation key of the menu heading the list is put under; null = not in the menu
     * @param string|null $role who the pages are for, e.g. "ROLE_ADMIN"; null = everybody
     * @param int $priority place in the menu, lower comes first
     * @param list<RecordAction> $actions what else can be done with a record - see RecordAction
     * @param list<LayoutNode|string> $layout how the forms and the details are arranged - see CrudLayout; empty = one
     *     field under another
     * @param list<LayoutNode|string>|null $formLayout replaces "layout" in the add and edit forms
     * @param list<LayoutNode|string>|null $detailsLayout replaces "layout" on the details page and in the drawer
     * @param string|null $listHead a Twig component shown in the list above its rows, given the "filters" in use and
     *     the "resource"; rendered inside the live list, so a click on it can set a filter (live action "filter")
     * @param string|null $listActions a Twig component shown in the head of the list page, next to "new", given the
     *     "filters" of the address and the "resource" - buttons that depend on the filter in use
     * @param CrudListedIn|null $listedIn no list page of its own: the records are shown in the list of another resource
     *     - see CrudListedIn; $section, the list in the palette and $listHead do not apply then
     * @param string $scope the panel (route scope) the pages are in, e.g. "panel" or "admin": the "byfareska_crud"
     *     route import of that scope puts its prefix and host before $path, and the scope has its menu, palette and
     *     layout
     * @param bool $own the one record is the one signed in (their id): no list, create nor delete, pages without an id
     *     in the address
     * @param bool|null $preview a click on a row of the list opens the record in the preview drawer; false = the
     *     first column leads to the details page (or to the edit form) and the rest of the row is not to be clicked;
     *     null = byfareska_crud.preview
     * @param list<BulkAction> $bulkActions what can be done with the records chosen in the list - see BulkAction; with
     *     one, the rows can be chosen
     * @param non-empty-list<int>|null $perPage how many records a page of the list can have, to choose from below it;
     *     one alone = no choice; the largest is the most a page can have; null = byfareska_crud.per_page
     * @param int|null $defaultPerPage which of them the list shows at first; null = byfareska_crud.default_per_page
     *     when it is one of them, else the first
     * @param list<string> $filtersInView the filters of the list always in view, by the name of their field, in the
     *     order of the list form; the others wait behind "more filters". Empty = all of them behind "filters" (the
     *     search is always in view)
     * @param array<string, string> $roles who may do an operation, besides $role, by the value of its CrudOperation:
     *     ['create' => 'ROLE_MANAGER', 'delete' => 'ROLE_MANAGER'] - the others are for everybody the resource is for.
     *     Asked with the resource as the subject (a voter of the application can decide), once a request; what is
     *     refused is not offered (its buttons, menu and palette entries go away) and its pages answer 403
     * @param string $listComponent the live component of the list: Crud:List, or one of the application's own extending
     *     CrudList (more live actions) - see CrudList
     * @param string $formComponent the live component of the add and edit forms: Crud:Form, or one of the application's
     *     own extending CrudForm - see CrudForm
     * @param list<string> $hidden properties the details never show, also when "details" names them: what the record
     *     carries for a guard or an action and nobody is to see. Without "details" every property is shown but these
     *     and those whose name looks secret (SECRET: password, token, hash...)
     * @param array<string, string> $pageTemplates the templates of its pages in place of the theme's, by page: "list",
     *     "show", "form" (add and edit), "page" (AsCrudPage) => a template of the application's, which may extend
     *     "@CrudTheme/crud/<page>.html.twig" and change a block of it
     * @param string|null $idPattern what the id in the address is made of, a regular expression: "\\d+" for numbers, so
     *     /products/export (a route of the application's own) is not taken for a record; null = anything but "/"
     * @param list<AfterSave> $afterSave the buttons the form has besides "save", in order: AfterSave::New ("save and
     *     add another", on the add form), AfterSave::Edit ("save and keep editing") - where they lead is CrudReturn's
     *     (CrudSaveReturn)
     * @param list<CrudOperation> $refused what is taken away for a request (see refusing()), whatever the source can
     *     do - not to be declared: the routes are there, the pages answer 403
     * @param array<string, string> $requirements what the parameters of the application in its path are made of, a
     *     regular expression by parameter: ['category' => '\\d+'] for /categories/{category}/products
     * @param array<string, mixed> $options what parts of the application know the resource by, the bundle does not
     *     read them: ['export' => true, 'audit' => 'full'] - an extension, a listener, a page of yours reads them
     *     (option()) and a CrudDefinitionExtension may change them
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $icon,
        ?CrudSource $source = null,
        ?string $list = null,
        ?string $read = null,
        ?string $create = null,
        ?string $update = null,
        ?string $delete = null,
        array $columns = [],
        array $details = [],
        public ?string $theme = null,
        public array $palette = [CrudOperation::List, CrudOperation::Create, CrudOperation::Read],
        ?string $search = null,
        public array $title = [],
        public ?string $section = null,
        public ?string $role = null,
        public int $priority = 0,
        public array $actions = [],
        public array $fields = [],
        array $layout = [],
        ?array $formLayout = null,
        ?array $detailsLayout = null,
        public ?string $listHead = null,
        public ?CrudListedIn $listedIn = null,
        public ?string $listActions = null,
        public string $scope = self::DEFAULT_SCOPE,
        public bool $own = false,
        public ?bool $preview = null,
        public array $bulkActions = [],
        public ?array $perPage = null,
        public array $filtersInView = [],
        public ?int $defaultPerPage = null,
        public array $roles = [],
        public array $refused = [],
        public string $listComponent = 'Crud:List',
        public string $formComponent = 'Crud:Form',
        public array $hidden = [],
        public array $afterSave = [],
        public ?string $idPattern = null,
        public array $pageTemplates = [],
        public array $options = [],
        public array $requirements = [],
    ) {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": the name is lower-case letters, digits and "_", starting with a letter - '
                . 'routes, texts and blocks go by it.',
                $name,
            ));
        }
        if (in_array($name, self::RESERVED_NAMES, true)) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": its texts would be crud.%1$s.*, which are the bundle\'s own - name it otherwise '
                . '("%1$ss", "promo_%1$s").',
                $name,
            ));
        }
        // The pages take what they need of the bundle from the route alone: a parameter of the path named like one of
        // the bundle's ({crud}, {id}, {_scope}...) would let the address choose it instead. Those of the application
        // ({category}) are its own - see PageParameters.
        $parameters = preg_match_all('/\{([^{}]*)\}/', $path, $found) > 0 ? $found[1] : [];
        $unnamed = preg_replace('/\{[^{}]*\}/', '', $path);
        $application = static fn (string $parameter): bool => preg_match(self::PARAMETER, $parameter) === 1
            && !in_array($parameter, PageParametersListener::RESERVED, true);
        if (
            !str_starts_with($path, '/')
            || strpbrk((string) $unnamed, '{}') !== false
            || !array_all($parameters, $application)
        ) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": the path "%s" starts with "/"; a parameter of it is a name of the application\'s '
                . '({category}), none of the bundle\'s (%s) nor one starting with "_".',
                $name,
                $path,
                implode(', ', PageParametersListener::RESERVED),
            ));
        }
        if ($source instanceof CrudSource && ($list ?? $read ?? $create ?? $update ?? $delete) !== null) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s" has a source: its DTOs go into it, not next to it.',
                $name,
            ));
        }
        if ($parameters !== [] && $section !== null) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": its path has parameters (%s), so it is no page of the menu ("section"): it is '
                . 'reached from a page that knows them, e.g. a RouteAction of a record.',
                $name,
                implode(', ', $parameters),
            ));
        }
        $this->source = $source ?? new DtoSource($list, $read, $create, $update, $delete);
        $this->search = $search ?? $this->source->searchField();

        // What the source can do: what a request refuses does not change what the resource is.
        $listsOrAdds = $this->offers(CrudOperation::List)
            || $this->offers(CrudOperation::Create)
            || $this->offers(CrudOperation::Delete)
            || $listedIn instanceof CrudListedIn;
        if ($own && $listsOrAdds) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s" is the record of the one signed in ("own"): it has no list, create, delete nor '
                . 'listedIn.',
                $name,
            ));
        }
        if ($own && !$this->offers(CrudOperation::Read) && !$this->offers(CrudOperation::Update)) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s" is the record of the one signed in ("own"): it needs read or update.',
                $name,
            ));
        }
        if (!$own && !$this->offers(CrudOperation::List)) {
            throw new InvalidArgumentException(sprintf('CRUD resource "%s" needs a list (or "own").', $name));
        }
        $names = [];
        foreach ($actions as $action) {
            if (!$action instanceof RecordAction) {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": %s is no action, only a %s.',
                    $name,
                    get_debug_type($action),
                    RecordAction::class,
                ));
            }
            if (isset($names[$action->name])) {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s" has the action "%s" twice.',
                    $name,
                    $action->name,
                ));
            }
            $names[$action->name] = true;
        }
        $bulkNames = [];
        foreach ($bulkActions as $action) {
            if (!$action instanceof BulkAction) {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": %s is no bulk action, only a %s.',
                    $name,
                    get_debug_type($action),
                    BulkAction::class,
                ));
            }
            if (isset($bulkNames[$action->name])) {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s" has the bulk action "%s" twice.',
                    $name,
                    $action->name,
                ));
            }
            $bulkNames[$action->name] = true;
        }
        $positive = static fn (mixed $count): bool => is_int($count) && $count > 0;
        if ($perPage !== null && ($perPage === [] || !array_is_list($perPage) || !array_all($perPage, $positive))) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": perPage is a list of positive numbers.',
                $name,
            ));
        }
        if ($defaultPerPage !== null && !in_array($defaultPerPage, $perPage ?? [$defaultPerPage], true)) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": defaultPerPage %d is none of perPage (%s).',
                $name,
                $defaultPerPage,
                implode(', ', $perPage ?? []),
            ));
        }
        if ($defaultPerPage !== null && $defaultPerPage < 1) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": defaultPerPage is a positive number.',
                $name,
            ));
        }
        if (!array_all($filtersInView, static fn (mixed $filter): bool => is_string($filter))) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": filtersInView is a list of the names of filters.',
                $name,
            ));
        }
        foreach ($roles as $operation => $operationRole) {
            if (!is_string($operation) || !CrudOperation::tryFrom($operation) instanceof CrudOperation) {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": roles are by operation (%s), not %s.',
                    $name,
                    implode(', ', array_column(CrudOperation::cases(), 'value')),
                    json_encode($operation),
                ));
            }
            if (!is_string($operationRole) || $operationRole === '') {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": the role of "%s" is a role, e.g. "ROLE_MANAGER".',
                    $name,
                    $operation,
                ));
            }
        }
        foreach ($pageTemplates as $page => $template) {
            if (!in_array($page, ['list', 'show', 'form', 'page'], true) || !is_string($template) || $template === '') {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": pageTemplates names a template for "list", "show", "form" or "page".',
                    $name,
                ));
            }
        }
        if (!array_all($afterSave, static fn (mixed $then): bool => $then instanceof AfterSave)) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": afterSave is a list of %s cases.',
                $name,
                AfterSave::class,
            ));
        }
        if (!array_all($hidden, static fn (mixed $field): bool => is_string($field))) {
            throw new InvalidArgumentException(sprintf('CRUD resource "%s": hidden is a list of properties.', $name));
        }
        foreach ($requirements as $parameter => $pattern) {
            if (!in_array($parameter, $parameters, true) || !is_string($pattern) || $pattern === '') {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": requirements are by a parameter of its path (%s), each a regular expression.',
                    $name,
                    implode(', ', $parameters) ?: 'it has none',
                ));
            }
        }
        if (!array_all(array_keys($options), static fn (int|string $option): bool => is_string($option))) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": options are by name, ["export" => true].',
                $name,
            ));
        }
        if ($listComponent === '' || $formComponent === '') {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": listComponent and formComponent name live components.',
                $name,
            ));
        }
        foreach ($refused as $operation) {
            if (!$operation instanceof CrudOperation) {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": %s is no operation to refuse, only a case of %s.',
                    $name,
                    get_debug_type($operation),
                    CrudOperation::class,
                ));
            }
        }
        foreach ($palette as $operation) {
            if (!$operation instanceof CrudOperation) {
                throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": %s is no operation for the palette, only a case of %s.',
                    $name,
                    get_debug_type($operation),
                    CrudOperation::class,
                ));
            }
        }

        $this->columns = $this->columnsFrom($columns);
        $this->details = $this->columnsFrom($details);
        $this->formLayout = $formLayout ?? $layout;
        $this->detailsLayout = $detailsLayout ?? $layout;
        CrudLayout::validate($this->formLayout);
        CrudLayout::validate($this->detailsLayout);
    }

    /**
     * The same resource with some of what it shows changed, by the names of the arguments of the constructor - what a
     * {@see \Byfareska\Crud\Resource\CrudDefinitionExtension} returns:
     *
     *     $crud->with(columns: [...$crud->columns, $crud->column('margin', 'money')])
     *
     * Columns and details take what the constructor takes, and Column objects as they are. Only the arguments of
     * CHANGEABLE: what the routes are made of stays, and so does who the resource is for. Checked like a declaration.
     *
     * @throws InvalidArgumentException an argument that is not to be changed, or a value the constructor refuses
     */
    public function with(mixed ...$changes): self
    {
        $unchangeable = array_diff(array_keys($changes), self::CHANGEABLE);
        if ($unchangeable !== []) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": %s cannot be changed, only %s.',
                $this->name,
                implode(', ', array_map(strval(...), $unchangeable)),
                implode(', ', self::CHANGEABLE),
            ));
        }
        if (array_key_exists('source', $changes)) {
            $this->assertSameRecords($changes['source']);
        }

        return $this->rebuilt($changes);
    }

    /**
     * A source given to with() is one of the same records, by the same driver, with the same operations - those
     * the routes were made for: its filters, sorting, search, forms, entity manager or count limit may differ
     * (`$crud->with(source: $crud->source->with(filters: [...]))`), its entity may not.
     */
    private function assertSameRecords(mixed $source): void
    {
        if (!$source instanceof CrudSource || !$this->sameRecords($this->source, $source)) {
            throw new InvalidArgumentException(sprintf(
                'CRUD resource "%s": the source is changed only for one of the same class, entity, driver and '
                . 'operations - what its routes were made for.',
                $this->name,
            ));
        }
    }

    /**
     * Whether two sources are of the same records: the same class, driver, entity and operations - what the routes
     * were made for (of a DtoSource, the same DTOs). Not the same object: a CrudDefinitionExtension gives one changed
     * by with().
     */
    private function sameRecords(CrudSource $one, CrudSource $other): bool
    {
        return $one === $other || (
            $one::class === $other::class
            && $one->driver() === $other->driver()
            && array_all(
                CrudOperation::cases(),
                static fn (CrudOperation $operation): bool => $one->supports($operation)
                    === $other->supports($operation),
            )
            && (get_object_vars($one)['entity'] ?? null) === (get_object_vars($other)['entity'] ?? null)
            // The use cases of the same operations: another DTO is another use case.
            && (!$one instanceof DtoSource || !$other instanceof DtoSource || array_all(
                CrudOperation::cases(),
                static fn (CrudOperation $operation): bool => $one->dto($operation) === $other->dto($operation),
            ))
        );
    }

    /**
     * The same resource without some operations for the request being answered, whatever its source can do - what a
     * {@see \Byfareska\Crud\Resource\CrudDefinitionExtension} returns for "this plan does not delete", and what the
     * bundle does for an operation of $roles the one asking has not. can() says no to them: their buttons, menu and
     * palette entries go away, and their pages and live actions answer 403. What one extension refused, the next
     * cannot give back.
     */
    public function refusing(CrudOperation ...$operations): self
    {
        $refused = $this->refused;
        foreach ($operations as $operation) {
            if (!in_array($operation, $refused, true)) {
                $refused[] = $operation;
            }
        }

        return $refused === $this->refused ? $this : $this->rebuilt(['refused' => $refused]);
    }

    /** Whether the request refuses it (see refusing()). */
    public function isRefused(CrudOperation $operation): bool
    {
        return in_array($operation, $this->refused, true);
    }

    /**
     * An option of the application's (AsCrud::$options): `$crud->option('export', false)`.
     */
    public function option(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
    }

    /**
     * The role an operation needs besides $role; null = none.
     */
    public function roleOf(CrudOperation $operation): ?string
    {
        return $this->roles[$operation->value] ?? null;
    }

    /**
     * @param array<mixed> $changes by the names of the arguments of the constructor
     */
    private function rebuilt(array $changes): self
    {
        $current = [
            'name' => $this->name,
            'path' => $this->path,
            'icon' => $this->icon,
            'source' => $this->source,
            'columns' => $this->columns,
            'details' => $this->details,
            'theme' => $this->theme,
            'palette' => $this->palette,
            'search' => $this->search,
            'title' => $this->title,
            'section' => $this->section,
            'role' => $this->role,
            'priority' => $this->priority,
            'actions' => $this->actions,
            'fields' => $this->fields,
            'formLayout' => $this->formLayout,
            'detailsLayout' => $this->detailsLayout,
            'listHead' => $this->listHead,
            'listedIn' => $this->listedIn,
            'listActions' => $this->listActions,
            'scope' => $this->scope,
            'own' => $this->own,
            'preview' => $this->preview,
            'bulkActions' => $this->bulkActions,
            'perPage' => $this->perPage,
            'filtersInView' => $this->filtersInView,
            'defaultPerPage' => $this->defaultPerPage,
            'roles' => $this->roles,
            'refused' => $this->refused,
            'listComponent' => $this->listComponent,
            'formComponent' => $this->formComponent,
            'hidden' => $this->hidden,
            'afterSave' => $this->afterSave,
            'idPattern' => $this->idPattern,
            'pageTemplates' => $this->pageTemplates,
            'options' => $this->options,
            'requirements' => $this->requirements,
        ];

        // @phpstan-ignore argument.type (checked by the constructor, like a declaration)
        return new self(...array_replace($current, $changes));
    }

    /**
     * Whether another definition is this resource with only what with() may change changed: its routes, its source
     * and its roles are the same, and what it refuses is no less.
     */
    public function isSameResourceAs(self $other): bool
    {
        return $other->name === $this->name
            && $other->path === $this->path
            && $other->scope === $this->scope
            && $other->own === $this->own
            && $this->sameRecords($this->source, $other->source)
            && $other->listedIn === $this->listedIn
            && $other->role === $this->role
            && $other->roles === $this->roles
            && array_all($this->refused, $other->isRefused(...));
    }

    /**
     * The translation key of a text of this resource: "title" (the list), "new" and "edit" (the pages).
     */
    public function key(string $text): string
    {
        return sprintf('crud.%s.%s', $this->name, $text);
    }

    /**
     * The fields a record is named by: $title, else the first column.
     *
     * @return list<string>
     */
    public function titleFields(): array
    {
        if ($this->title !== []) {
            return $this->title;
        }

        return isset($this->columns[0]) ? [$this->columns[0]->field] : [];
    }

    /**
     * What a record (an item of the list or the properties of what "read" returns) is named by; null = it has nothing.
     *
     * @param array<string, mixed> $item
     */
    public function titleOf(array $item): ?string
    {
        $parts = [];
        foreach ($this->titleFields() as $field) {
            $value = $item[$field] ?? null;
            if ($value instanceof JsonSerializable) {
                $value = $value->jsonSerialize();
            }
            if (is_scalar($value) || $value instanceof Stringable) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * A field of this resource with its heading, the translation crud.<name>.field.<field>.name.
     */
    public function column(string $field, ?string $type = null, ?ValueFormat $format = null): Column
    {
        return new Column($field, $this->key(sprintf('field.%s.name', $field)), $type, $format);
    }

    /**
     * @param array<int|string, mixed> $columns
     *
     * @return list<Column>
     */
    private function columnsFrom(array $columns): array
    {
        $result = [];
        foreach ($columns as $key => $value) {
            // 'name' is a field; 'email' => 'email' is a field with its type, 'stock' => new X() with its format; a
            // Column as it is (what with() is given back).
            $result[] = match (true) {
                is_int($key) && $value instanceof Column => $value,
                is_int($key) && is_string($value) => $this->column($value),
                is_string($key) && is_string($value) => $this->column($key, $value),
                is_string($key) && $value instanceof ValueFormat => $this->column($key, format: $value),
                default => throw new InvalidArgumentException(sprintf(
                    'CRUD resource "%s": the column %s is a field, a Column, or a field => its type or a %s.',
                    $this->name,
                    json_encode($key),
                    ValueFormat::class,
                )),
            };
        }

        return $result;
    }

    /**
     * Whether the details show a property: not one of "hidden" and - unless "details" names it - not one whose name
     * looks secret.
     */
    public function showsInDetails(string $field): bool
    {
        if (in_array($field, $this->hidden, true)) {
            return false;
        }

        return in_array($field, array_column($this->details, 'field'), true) || !self::looksSecret($field);
    }

    /**
     * Whether the name of a property has a word of SECRET, or two of them one after another ("api key").
     */
    public static function looksSecret(string $field): bool
    {
        // "passwordHash", "reset_token", "APIKey" - its words, in lower case.
        $spaced = preg_replace(
            ['/([a-z0-9])([A-Z])/', '/([A-Z]+)([A-Z][a-z])/', '/[^A-Za-z0-9]+/'],
            ['$1 $2', '$1 $2', ' '],
            $field,
        );
        $words = preg_split('/\s+/', strtolower(trim((string) $spaced)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $index => $word) {
            $pair = $word . ' ' . ($words[$index + 1] ?? '');
            if (in_array($word, self::SECRET, true) || in_array($pair, self::SECRET, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The parameters of its path, the application's own: ['category'] for /categories/{category}/products.
     *
     * @return list<string>
     */
    public function pathParameters(): array
    {
        return preg_match_all('/\{([^{}]+)\}/', $this->path, $found) > 0 ? $found[1] : [];
    }

    /**
     * Whether the command palette offers this operation: it is named in "palette" and the resource can do it - not for
     * a resource whose path has parameters: its pages are reached from a page that knows them.
     */
    public function inPalette(CrudOperation $operation): bool
    {
        if (!in_array($operation, $this->palette, true) || $this->pathParameters() !== []) {
            return false;
        }

        // The page of one's own record is an entry of its own, under "read".
        if ($this->own) {
            return $operation === CrudOperation::Read
                && ($this->can(CrudOperation::Read) || $this->can(CrudOperation::Update));
        }

        return match ($operation) {
            CrudOperation::List => $this->isListed() && $this->can(CrudOperation::List),
            CrudOperation::Create => $this->can(CrudOperation::Create),
            // A record is found through the list and opened on its details page.
            CrudOperation::Read => $this->can(CrudOperation::Read)
                && $this->can(CrudOperation::List)
                && $this->search !== null,
            CrudOperation::Update, CrudOperation::Delete => false,
        };
    }

    /**
     * Whether the resource can do it in this request: its source can (see offers()) and the request does not refuse it
     * (see refusing() - e.g. an operation of $roles the one asking has not).
     */
    public function can(CrudOperation $operation): bool
    {
        return $this->offers($operation) && !$this->isRefused($operation);
    }

    /**
     * Whether the resource does it at all, whoever asks: its source says. An "own" resource has no list whatever its
     * source can do. What the routes are made of.
     */
    public function offers(CrudOperation $operation): bool
    {
        if (
            $this->own
            && in_array($operation, [CrudOperation::List, CrudOperation::Create, CrudOperation::Delete], true)
        ) {
            return false;
        }

        return $this->source->supports($operation);
    }

    /** Whether the resource has a list page of its own. */
    public function isListed(): bool
    {
        return !$this->own && !$this->listedIn instanceof CrudListedIn;
    }

    /**
     * The page of an "own" resource the menu and the palette lead to: its details, else its edit form.
     */
    public function ownPage(): string
    {
        // A page there is (offers()): the details, unless only the form is left to the one asking.
        $show = $this->offers(CrudOperation::Read)
            && ($this->can(CrudOperation::Read) || !$this->can(CrudOperation::Update));

        return $this->route($show ? 'show' : 'edit');
    }

    /**
     * The parameters of the route of a record's page: its id, except for an "own" resource, whose addresses have none.
     *
     * @param array<string, mixed> $parameters what else goes into the address
     *
     * @return array<string, mixed>
     */
    public function recordParameters(?string $id, array $parameters = []): array
    {
        return $this->own || $id === null ? $parameters : ['id' => $id] + $parameters;
    }

    public function route(string $page): string
    {
        return sprintf('crud_%s_%s', $this->name, $page);
    }
}
