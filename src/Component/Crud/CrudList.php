<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Crud;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\BulkActions;
use Byfareska\Crud\Action\BulkResult;
use Byfareska\Crud\Action\BulkSelection;
use Byfareska\Crud\Action\DeleteAction;
use Byfareska\Crud\Action\RecordAction;
use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Filter\Compared;
use Byfareska\Crud\Panel\ScopedComponent;
use Byfareska\Crud\Resource\Column;
use Byfareska\Crud\Resource\CrudAccess;
use Byfareska\Crud\Resource\ConfirmationInput;
use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\CrudListQuery;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Resource\CrudToast;
use Byfareska\Crud\Resource\FailureMessage;
use Byfareska\Crud\Resource\IdMapper;
use Byfareska\Crud\Resource\PageSizes;
use Byfareska\Crud\Resource\RecordDeletion;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordRows;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use Byfareska\Crud\Routing\PageParameters;
use Byfareska\Crud\Security\Actor;
use Byfareska\Crud\UseCase\CountsApproximately;
use Byfareska\Crud\UseCase\Page;
use Byfareska\Crud\UseCase\Sort;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\Attribute\PreDehydrate;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Byfareska\Crud\Event\ListStateChanged;
use Byfareska\Crud\Resource\ListDefaults;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * The list of any #[AsCrud] resource: filters, sorting, pagination, the row actions and the preview of a record in a
 * drawer. The filters are the list form of the resource's driver (CrudDriver::listForm()) - for the "dto" driver the
 * form type of the list DTO, so the panel validates and converts them exactly like the API does.
 * Filters and the order (query string) and the page (path) are mirrored in the URL, so a filtered list can be linked
 * to.
 *
 * A resource may have a list of its own - more live actions (a toggle in a cell, an order dragged, an export of what is
 * filtered), other computed values for its theme blocks: a class extending this one, named in #[AsCrud(listComponent:)]
 * and drawn by the same template. Services its actions need are arguments of the actions themselves (a LiveAction
 * gets them autowired); getDefinition() asks who may see it, form() is the submitted list form:
 *
 *     #[AsLiveComponent('App:ProductList', template: CrudList::TEMPLATE)]
 *     final class ProductList extends CrudList
 *     {
 *         #[LiveAction]
 *         public function toggleActive(#[LiveArg] string $id, ProductRepository $products): void { ... }
 *     }
 *
 * @phpstan-import-type RecordDetails from CrudRecords
 */
#[AsLiveComponent('Crud:List', template: self::TEMPLATE)]
class CrudList implements ScopedComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /** The template of the list, in the theme in use - also that of a list of a resource's own. */
    public const string TEMPLATE = 'components/Crud/List.html.twig';

    /** Fields of the list form that the list itself fills in; all the others are filters. */
    private const array RESERVED = CrudListQuery::CONTROLLED;

    /** The field of the list form that says what the list is ordered by - see Sort. */
    private const string SORT = 'sort';

    /** Name of the resource, as in #[AsCrud]. */
    #[LiveProp]
    public string $resource = '';

    /**
     * Filters the list always has, set where it is drawn and never by the browser: the lines of the order whose details
     * show them (`fixedFilters: {order: id}`). Not in the toolbar, not taken out by "clear filters", not changed by a
     * request - a bulk action gets only records they match.
     *
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $fixedFilters = [];

    /**
     * Whether the state of the list (filters, page, size, order, preview) is in the address of the page. A list
     * embedded on a page of its own (two lists on the details of a customer, a list on a dashboard) keeps it to itself,
     * or under $urlPrefix.
     */
    #[LiveProp]
    public bool $inUrl = true;

    /**
     * What the state of the list is named by in the address: "invoices" makes ?invoices_filters[...]&invoices_page=2,
     * so two lists of one page keep theirs apart; null = the names of a page of the list itself (and the page in the
     * path).
     */
    #[LiveProp]
    public ?string $urlPrefix = null;

    /**
     * Values of the filter fields, as typed.
     *
     * @var array<string, mixed>
     */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'filters'), modifier: 'mapToUrl')]
    public array $filters = [];

    /**
     * The filters the current page number belongs to. An "onUpdated" hook cannot watch `filters`: its keys are
     * written one by one (filters.query) and are not known here.
     *
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $pagedFilters = [];

    /**
     * Whether the panel of the filters is open; null = while one of them is in use. The search is always in view.
     */
    #[LiveProp(writable: true)]
    public ?bool $filtersOpen = null;

    /**
     * Part of the path, not of the query string: /users/page/3 (the route is built by CrudRoutes).
     */
    #[LiveProp(writable: true, url: new UrlMapping(as: 'page', mapPath: true), modifier: 'mapToUrl')]
    public int $page = 1;

    /**
     * How many records a page has, one of the sizes of the resource (PageSizes); null = the one it shows at first,
     * which then is not in the address either. In the address as "size" (?size=30).
     */
    #[LiveProp(writable: true, onUpdated: 'returnToFirstPage', url: new UrlMapping(as: 'size'), modifier: 'mapToUrl')]
    public ?int $perPage = null;

    /**
     * What the list is ordered by, as the list form takes it and with commas in between ("-name,email"); null = the
     * order of the list form itself, which then is not in the address either.
     */
    #[LiveProp(url: new UrlMapping(as: 'sort'), modifier: 'mapToUrl')]
    public ?string $sort = null;

    /**
     * The record shown in the preview drawer, as in a URL; null while the drawer is closed.
     * Part of the address (?preview=...), so a link to the list can open with a record in view.
     */
    #[LiveProp(url: new UrlMapping(as: 'preview'), modifier: 'mapToUrl')]
    public ?string $previewId = null;

    /**
     * The records chosen for a bulk action, by id; also those chosen on another page of the list.
     *
     * @var list<string>
     */
    #[LiveProp(writable: true)]
    public array $selected = [];

    /**
     * Every record the filters match is chosen, on every page ("select all N matching") - not a list of ids, which
     * could be thousands. Forgotten when the filters change.
     */
    #[LiveProp]
    public bool $allMatching = false;

    /** Whether the one looking changed the filters, the order or the size in this request (ListStateChanged). */
    private bool $stateChanged = false;

    /**
     * The list forms submitted in this request, by what they were submitted with: a render asks for the form many times
     * (the fields, the order, the filters in use...), and building and submitting it again each time would ask its
     * driver again - a choice of every related entity, a query.
     *
     * @var array<string, FormInterface<mixed>>
     */
    private array $forms = [];

    /**
     * The filter fields of those forms, by the same key.
     *
     * @var array<string, list<FormView>>
     */
    private array $filterFieldViews = [];

    /**
     * The bundle adds to these (at the end, with defaults): a class extending this one gets a service of its own
     * by a method marked #[Required], or as an argument of its live action - not by a constructor of its own.
     *
     * @param iterable<ListDefaults> $listDefaults
     */
    public function __construct(
        protected readonly CrudRegistry $registry,
        protected readonly CrudRecords $records,
        protected readonly CrudDrivers $drivers,
        protected readonly TranslatorInterface $translator,
        protected readonly FailureMessage $failureMessage,
        protected readonly CrudAccess $access,
        protected readonly IdMapper $ids,
        protected readonly RecordActions $actions,
        protected readonly RecordGuards $guards,
        protected readonly RecordDeletion $deletion,
        protected readonly RecordRows $rows,
        protected readonly BulkActions $bulkActions,
        protected readonly PageSizes $pageSizes,
        #[Autowire(param: 'byfareska_crud.preview')]
        protected readonly bool $preview = true,
        protected readonly ?CrudListQuery $query = null,
        protected readonly ?RequestStack $requestStack = null,
        protected readonly ?ConfirmationInput $confirmation = null,
        #[AutowireIterator(ListDefaults::TAG)]
        protected readonly iterable $listDefaults = [],
        protected readonly ?EventDispatcherInterface $events = null,
        protected readonly ?PageParameters $pageParameters = null,
        protected readonly ?Actor $actor = null,
    ) {
    }

    /**
     * The address a prop of the state of the list is kept in: as declared, under $urlPrefix, or none ($inUrl).
     */
    public function mapToUrl(LiveProp $prop): LiveProp
    {
        if (!$this->inUrl) {
            return $prop->withUrl(false);
        }
        if ($this->urlPrefix === null || $this->urlPrefix === '') {
            return $prop;
        }
        // Every prop of the state is mapped under a name of its own ("as"): the prop does not know its name.
        $mapping = $prop->url();
        $name = $mapping instanceof UrlMapping ? (string) $mapping->as : '';

        // Not in the path: that is the page's own.
        return $prop->withUrl(new UrlMapping(as: $this->urlPrefix . '_' . $name));
    }

    /**
     * What the list form is submitted with: the filters typed, and over them those the list always has.
     *
     * @return array<string, mixed>
     */
    public function effectiveFilters(): array
    {
        return array_replace($this->filters, $this->fixedFilters);
    }

    /**
     * What the list shows for its filters and order - the same for an export or a message handled later.
     */
    protected function listQuery(): CrudListQuery
    {
        return $this->query ?? new CrudListQuery(
            $this->drivers,
            $this->access,
            $this->pageSizes,
            $this->actions,
            $this->guards,
            $this->rows,
            $this->ids,
        );
    }

    public function panelScope(): ?string
    {
        return $this->resource === '' ? null : $this->registry->get($this->resource)->scope;
    }

    public function getDefinition(): AsCrud
    {
        $crud = $this->registry->get($this->resource);
        // The component is reached without its page too (the live endpoint).
        $this->access->assertGranted($crud, CrudOperation::List);

        return $crud;
    }

    /**
     * What else can be done with a record, for the one looking - see RecordAction.
     *
     * @return list<RecordAction>
     */
    public function getActions(): array
    {
        return $this->actions->of($this->getDefinition());
    }

    /**
     * What can be done with a record of the list, in order - see RecordActions - so a theme only draws them
     * (crud_action()): a button on the row when "inline", else an item of the row's menu.
     *
     * @param array<string, mixed> $item
     * @param Page $list the page the item is on (computed.result: asked again, the list would be read again)
     *
     * @return list<ActionButton>
     */
    public function rowActions(array $item, Page $list): array
    {
        return $this->actions->buttons(
            new RecordView($this->getDefinition(), $this->idOf($item), $item, RecordScreen::List, $list),
        );
    }

    /**
     * The attributes of the row of an item, of the RecordRow services of the resource.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, string|int|bool|null>
     */
    public function rowAttributes(array $item, Page $list): array
    {
        return $this->rows->attributes(
            new RecordView($this->getDefinition(), $this->idOf($item), $item, RecordScreen::List, $list),
        );
    }

    /**
     * Whether the rows can be chosen: there is a bulk action for the one looking.
     */
    public function isSelectable(): bool
    {
        return $this->bulkActions->of($this->getDefinition()) !== [];
    }

    /**
     * What can be done with the records chosen - see BulkAction.
     *
     * @return list<ActionButton>
     */
    public function bulkButtons(Page $list): array
    {
        return $this->bulkActions->buttons($this->selection($list));
    }

    /**
     * How many records are chosen: those of their ids, or every match of the filters.
     */
    public function selectedCount(Page $list): int
    {
        return $this->allMatching ? $list->total : count($this->selected);
    }

    /**
     * Whether "select all N matching" is offered: the whole page is chosen, and the filters match more than that - a
     * number the list knows: not of a list counted only so far (DoctrineSource "countLimit"), too large to act on
     * whole.
     */
    public function canSelectAllMatching(Page $list): bool
    {
        return !$this->allMatching
            && (!$list instanceof CountsApproximately || !$list->more)
            && $this->pageSelection($list) === true
            && $list->total > count($this->selected)
            && $this->form($this->filters)->isValid();
    }

    /**
     * The records chosen, for a bulk action - every match of the filters read only when the action asks for them.
     */
    protected function selection(?Page $list = null, ?string $input = null): BulkSelection
    {
        $crud = $this->getDefinition();
        if (!$this->allMatching) {
            return new BulkSelection(
                $crud,
                $this->selected,
                $list,
                filters: $this->effectiveFilters(),
                sort: $this->sort,
                input: $input,
                parameters: $this->pageParameters?->all() ?? [],
                actor: $this->actor?->id(),
            );
        }

        // Gone through as they come only when a change cannot move them (one query of the ids); else gathered first.
        $streaming = $this->listQuery()->streams($crud)
            ? fn (): iterable => $this->listQuery()->readableIds($crud, $this->effectiveFilters(), $this->sort)
            : null;

        return new BulkSelection(
            $crud,
            list: $list,
            matching: $this->matchingIds(...),
            count: $list instanceof Page ? $list->total : null,
            filters: $this->effectiveFilters(),
            sort: $this->sort,
            streaming: $streaming,
            input: $input,
            parameters: $this->pageParameters?->all() ?? [],
            actor: $this->actor?->id(),
        );
    }

    /**
     * Of the ids chosen, those of records the list could show: there, in its scopes (a DoctrineScope, a tenant, a
     * parameter of the address), and allowed to be read by the guards of the resource. A resource that cannot be read
     * by id (no "read" operation) keeps them as they are - its bulk actions check them themselves.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    protected function visibleOf(array $ids): array
    {
        $crud = $this->getDefinition();
        if ($ids !== [] && $this->fixedFilters !== []) {
            // An embedded list (the lines of one order): only records of it, not of another order.
            $ofTheList = array_flip(iterator_to_array(
                $this->listQuery()->ids($crud, $this->fixedFilters),
                false,
            ));
            $ids = array_values(array_filter($ids, static fn (string $id): bool => isset($ofTheList[$id])));
        }
        if ($ids === [] || !$crud->can(CrudOperation::Read)) {
            return $ids;
        }

        // Those on the page in view are known by its items (which carry what the guards need): no record read again
        // for each. Only one chosen on another page is read by itself.
        $onPage = [];
        try {
            foreach ($this->getResult()->items as $item) {
                $onPage[$this->idOf($item)] = $item;
            }
        } catch (CrudFailure) {
            $onPage = [];
        }

        return array_values(array_filter($ids, function (string $id) use ($crud, $onPage): bool {
            try {
                $record = isset($onPage[$id])
                    ? new RecordView($crud, $id, $onPage[$id], RecordScreen::List)
                    : $this->records->view($crud, $id, RecordScreen::List);

                return $this->guards->allows(CrudOperation::Read, $record);
            } catch (CrudFailure) {
                // Gone, or not in its scopes.
                return false;
            }
        }));
    }

    /**
     * The ids of every record the filters match and its guards let be read - the records the list shows - read page
     * by page with the largest page size the resource has: what a bulk action of "all N matching" goes through. Read
     * before anything is done, so what the action changes does not move the pages under it. Filters the list does not
     * accept match nothing.
     *
     * @return list<string>
     */
    protected function matchingIds(): array
    {
        return iterator_to_array(
            $this->listQuery()->readableIds($this->getDefinition(), $this->effectiveFilters(), $this->sort),
            false,
        );
    }

    /**
     * Whether all, some or none of the records of the page are chosen: true, null, false.
     */
    public function pageSelection(Page $list): ?bool
    {
        if ($this->allMatching) {
            return true;
        }

        $ids = array_map($this->idOf(...), $list->items);
        $chosen = array_intersect($ids, $this->selected);

        return match (true) {
            $ids === [] || $chosen === [] => false,
            count($chosen) === count($ids) => true,
            default => null,
        };
    }

    /**
     * What can be done with the record of the preview drawer.
     *
     * @param RecordDetails $details
     *
     * @return list<ActionButton>
     */
    public function recordActions(array $details): array
    {
        return $this->actions->buttons(
            new RecordView($this->getDefinition(), $details['id'], $details['item'], RecordScreen::Preview),
        );
    }

    public function getResult(): Page
    {
        $crud = $this->getDefinition();
        $form = $this->form($this->filters);
        if (!$form->isValid()) {
            // A filter value the list does not accept (it may come from the URL): list everything, the field shows why.
            $form = $this->form([]);
        }
        if (!$form->isValid()) {
            throw new LogicException(sprintf(
                'The list of "%s" cannot be requested: %s',
                $this->resource,
                $form->getErrors(true),
            ));
        }

        // A list form without "page" asks for every record: they are paged here.
        return $this->listQuery()->pageOf($crud, $form, $this->page, $this->getPageSize());
    }

    /**
     * What a page of the list can have, to choose from below it.
     *
     * @return non-empty-list<int>
     */
    public function getPageSizes(): array
    {
        return $this->pageSizes->of($this->getDefinition());
    }

    /**
     * The filter fields, carrying the current values and what is wrong with them.
     *
     * @return list<FormView>
     */
    public function getFilterFields(): array
    {
        $key = $this->formKey($this->filters);
        if (isset($this->filterFieldViews[$key])) {
            return $this->filterFieldViews[$key];
        }

        $fields = [];
        foreach ($this->form($this->filters)->createView()->children as $name => $field) {
            if (!in_array($name, self::RESERVED, true) && !array_key_exists($name, $this->fixedFilters)) {
                $fields[] = $field;
            }
        }

        return $this->filterFieldViews[$key] = $fields;
    }

    /**
     * The filter fields in the head of the list: those taking a typed text to look for.
     *
     * @return list<FormView>
     */
    public function getSearchFields(): array
    {
        return array_values(array_filter($this->getFilterFields(), $this->isSearch(...)));
    }

    /**
     * The filter fields always in view, each under its label: those of AsCrud::$filtersInView.
     *
     * @return list<FormView>
     *
     * @throws LogicException a filter in view is no filter of the list
     */
    public function getInViewFields(): array
    {
        // A filter the list always has is no field of it.
        $inView = array_values(array_diff($this->getDefinition()->filtersInView, array_keys($this->fixedFilters)));
        $fields = array_values(array_filter(
            $this->labelledFields(),
            static fn (FormView $field): bool => in_array($field->vars['name'], $inView, true),
        ));
        if (count($fields) !== count($inView)) {
            $names = array_map(
                static fn (FormView $field): string => is_string($field->vars['name']) ? $field->vars['name'] : '',
                $this->labelledFields(),
            );
            throw new LogicException(sprintf(
                'CRUD resource "%s": filtersInView names %s, the filters of its list are %s.',
                $this->resource,
                json_encode(array_values(array_diff($inView, $names))),
                json_encode($names),
            ));
        }

        return $fields;
    }

    /**
     * The filter fields of the panel that opens on a click ("filters", or "more filters" next to those in view), each
     * under its label.
     *
     * @return list<FormView>
     */
    public function getPanelFields(): array
    {
        $inView = $this->getDefinition()->filtersInView;

        return array_values(array_filter(
            $this->labelledFields(),
            static fn (FormView $field): bool => !in_array($field->vars['name'], $inView, true),
        ));
    }

    /**
     * How many filters of the panel are in use - shown on the button that opens it.
     */
    public function getActiveFilterCount(): int
    {
        $form = $this->form($this->filters);
        $count = 0;
        foreach ($this->getPanelFields() as $field) {
            $name = $field->vars['name'];
            if (is_string($name) && $this->inUse($form, $name, $this->filters[$name] ?? null)) {
                ++$count;
            }
        }

        return $count;
    }

    public function isFiltersShown(): bool
    {
        return $this->filtersOpen ?? $this->getActiveFilterCount() > 0;
    }

    #[LiveAction]
    public function toggleFilters(): void
    {
        $this->filtersOpen = !$this->isFiltersShown();
    }

    /**
     * The columns whose heading orders the list: the fields the "sort" field of the list form offers.
     *
     * @return list<string>
     */
    public function getSortable(): array
    {
        $sorts = $this->sorts($this->form($this->filters));

        return array_values(array_filter(
            array_map(static fn (Column $column): string => $column->field, $this->getDefinition()->columns),
            static fn (string $field): bool => in_array($field, $sorts, true),
        ));
    }

    /**
     * What the list is ordered by right now, the most important first - also when nobody has chosen: the list form
     * says.
     *
     * @return list<Sort>
     */
    public function getSorted(): array
    {
        $form = $this->form($this->filters);
        $sorts = $form->has(self::SORT) ? $form->get(self::SORT)->getData() : null;

        return is_array($sorts) ? Sort::parseAll($sorts) : [];
    }

    /**
     * The same for the headings, by field: which way, and the place in the order when there is more than one.
     *
     * @return array<string, array{descending: bool, place: int|null}>
     */
    public function getSorting(): array
    {
        $sorted = $this->getSorted();
        $sorting = [];
        foreach ($sorted as $index => $sort) {
            $sorting[$sort->field] = [
                'descending' => $sort->isDescending(),
                'place' => count($sorted) > 1 ? $index + 1 : null,
            ];
        }

        return $sorting;
    }

    /**
     * Whether anything narrows the list - then there is something for "clear filters" to do.
     */
    public function isFiltered(): bool
    {
        $form = $this->form($this->filters);

        return array_any(
            $this->filters,
            fn (mixed $value, string|int $name): bool => $this->inUse($form, (string) $name, $value),
        );
    }

    /**
     * The filters as the address shows them: only those in use, without their empty parts. The component itself
     * keeps every one of them (completeFilters()), the address does not need to - what is missing comes back empty.
     *
     * @return array<string, mixed>
     */
    public function addressFilters(): array
    {
        $form = $this->form($this->filters);
        $filters = [];
        foreach ($this->filters as $name => $value) {
            if ($this->inUse($form, (string) $name, $value)) {
                $filters[$name] = self::withoutEmpty($value);
            }
        }

        return $filters;
    }

    private static function withoutEmpty(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $kept = array_filter(
            array_map(self::withoutEmpty(...), $value),
            static fn (mixed $part): bool => !in_array($part, [null, '', []], true),
        );

        return array_is_list($value) ? array_values($kept) : $kept;
    }

    /**
     * Whether a filter narrows the list. A field of parts (how it compares and what with) goes by what the form makes
     * of them - a comparison alone asks for nothing; any other by what was typed.
     *
     * @param FormInterface<mixed> $form
     */
    private function inUse(FormInterface $form, string $name, mixed $value): bool
    {
        if ($form->has($name) && $form->get($name)->getConfig()->getCompound()) {
            $field = $form->get($name);

            return !$field->isValid() || !Compared::isEmpty($field->getData());
        }

        return is_string($value) ? trim($value) !== '' : $value !== null && $value !== [];
    }

    /**
     * The filter fields each under its label: all but the search, in the head of the list, and a hidden one, set by
     * something else (#[AsCrud] listHead).
     *
     * @return list<FormView>
     */
    private function labelledFields(): array
    {
        return array_values(array_filter(
            $this->getFilterFields(),
            fn (FormView $field): bool => !$this->isSearch($field)
                && !in_array('hidden', $this->blockPrefixes($field), true),
        ));
    }

    private function isSearch(FormView $field): bool
    {
        return in_array('search', $this->blockPrefixes($field), true);
    }

    /**
     * @return list<string>
     */
    private function blockPrefixes(FormView $field): array
    {
        $prefixes = $field->vars['block_prefixes'] ?? [];

        return is_array($prefixes) ? array_values(array_filter($prefixes, is_string(...))) : [];
    }

    /**
     * Whether a click on a row opens the record in the preview drawer: AsCrud::$preview, else byfareska_crud.preview -
     * and only for those who may read the records.
     */
    public function hasPreview(): bool
    {
        $crud = $this->getDefinition();

        return ($crud->preview ?? $this->preview) && $crud->can(CrudOperation::Read);
    }

    /**
     * The record of the preview drawer - see CrudRecords::details().
     *
     * @return RecordDetails|null
     */
    public function getPreviewed(): ?array
    {
        if ($this->previewId === null || !$this->hasPreview()) {
            return null;
        }

        $crud = $this->getDefinition();

        try {
            $details = $this->records->details($crud, $this->previewId);
        } catch (CrudFailure) {
            // Deleted in the meantime: the drawer has nothing to show.
            return null;
        }

        // Not to be read by the one looking (RecordGuard): as if it were not there.
        $record = new RecordView($crud, $details['id'], $details['item'], RecordScreen::Preview);

        return $this->guards->allows(CrudOperation::Read, $record) ? $details : null;
    }

    /**
     * How many records a page has: the one chosen, when the resource offers it, else the first it offers.
     */
    public function getPageSize(): int
    {
        $crud = $this->getDefinition();

        return in_array($this->perPage, $this->pageSizes->of($crud), true)
            ? $this->perPage
            : $this->pageSizes->defaultOf($crud);
    }

    /**
     * Called when another size of a page is chosen: the page number said where in the old ones the list was.
     */
    public function returnToFirstPage(): void
    {
        $this->stateChanged = true;
        $this->page = 1;
        if ($this->perPage === $this->pageSizes->defaultOf($this->getDefinition())) {
            $this->perPage = null;
        }
    }

    /**
     * The id of a list item as it goes into a URL or an action.
     *
     * @param array<string, mixed> $item
     */
    public function idOf(array $item): string
    {
        return $this->ids->toId($item['id'] ?? null) ?? '';
    }

    /**
     * Sends every filter to the browser, also the ones not in use: the frontend refuses to write a key
     * (data-model="filters.query") that the `filters` it received does not have.
     */
    #[PreDehydrate]
    public function completeFilters(): void
    {
        $filters = [];
        foreach ($this->getFilterFields() as $field) {
            $name = $field->vars['name'];
            if (!is_string($name)) {
                continue;
            }
            // As typed when there is something typed: the form trims text, and sending that back would
            // take the space away from under the cursor of somebody typing "jan kowalski" slowly.
            $typed = $this->filters[$name] ?? null;
            if (($field->vars['compound'] ?? false) && is_scalar($typed)) {
                // A plain value (a link, a click on the list's head) the form took for one of the parts.
                $filters[$name] = array_map(
                    static fn (FormView $part): mixed => $part->vars['value'],
                    $field->children,
                );
            } elseif ($field->vars['compound'] ?? false) {
                // Every part (filters.price.comparison, filters.price.value...), null until something is chosen:
                // the form knows what comes first, and the address is left without it.
                $parts = array_fill_keys(array_keys($field->children), null);
                $filters[$name] = array_replace($parts, is_array($typed) ? array_intersect_key($typed, $parts) : []);
            } else {
                $filters[$name] = $typed ?? $field->vars['value'];
            }
        }

        $this->filters = $this->pagedFilters = $filters;
    }

    /**
     * What the list starts with when its address says nothing: the filters and the order of the first ListDefaults of
     * the resource that says them.
     */
    #[PostMount]
    public function applyDefaults(): void
    {
        if ($this->resource === '' || ($this->filters !== [] && $this->sort !== null)) {
            return;
        }
        $crud = $this->registry->get($this->resource);
        foreach ($this->listDefaults as $defaults) {
            if (!in_array($defaults->resource(), [$crud->name, ListDefaults::EVERY], true)) {
                continue;
            }
            if ($this->filters === []) {
                $this->filters = $defaults->filters($crud);
            }
            $this->sort ??= $defaults->sort($crud);
        }
    }

    /**
     * Tells the listeners of ListStateChanged what the one looking chose, once a request.
     */
    #[PreDehydrate(priority: -10)]
    public function tellStateChanged(): void
    {
        if (!$this->stateChanged || !$this->events instanceof EventDispatcherInterface) {
            return;
        }
        $this->stateChanged = false;
        $this->events->dispatch(new ListStateChanged(
            $this->getDefinition(),
            $this->addressFilters(),
            $this->sort,
            $this->perPage,
        ));
    }

    /**
     * A page number says nothing once the filters have changed.
     */
    #[PostHydrate]
    public function returnToFirstPageWhenFiltersChanged(): void
    {
        $this->selected = array_values(array_unique(array_filter($this->selected, is_string(...))));
        if ($this->filters !== $this->pagedFilters) {
            $this->stateChanged = true;
            $this->page = 1;
            // What was chosen may not be in the list any more, and "all matching" matched other filters.
            $this->selected = [];
            $this->allMatching = false;
        }
    }

    /**
     * Sets one filter (an empty value takes it out), as a click on something of the list's head does - see
     * AsCrud::$listHead. A name that is no filter field of the list is ignored.
     */
    #[LiveAction]
    public function filter(#[LiveArg] string $field, #[LiveArg] string $value = ''): void
    {
        if (!array_any($this->getFilterFields(), static fn (FormView $view): bool => $view->vars['name'] === $field)) {
            return;
        }

        if ($value === '') {
            unset($this->filters[$field]);
        } else {
            $this->filters[$field] = $value;
        }
        $this->page = 1;
        $this->stateChanged = true;
    }

    #[LiveAction]
    public function clearFilters(): void
    {
        $this->stateChanged = true;
        $this->filters = [];
        $this->page = 1;
        $this->selected = [];
        $this->allMatching = false;
    }

    /**
     * The checkbox in the heading: chooses every record of the page, or none of them when all are chosen already.
     */
    #[LiveAction]
    public function selectPage(): void
    {
        // Every match chosen: the heading takes them all back.
        if ($this->allMatching) {
            $this->clearSelection();

            return;
        }

        $result = $this->getResult();
        $ids = array_map($this->idOf(...), $result->items);
        $this->selected = $this->pageSelection($result) === true
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    #[LiveAction]
    public function clearSelection(): void
    {
        $this->selected = [];
        $this->allMatching = false;
    }

    /**
     * Chooses every record the filters match, on every page.
     */
    #[LiveAction]
    public function selectAllMatching(): void
    {
        $this->allMatching = true;
        $this->selected = [];
    }

    /**
     * Does a bulk action with the records chosen, then forgets them.
     */
    #[LiveAction]
    public function bulk(#[LiveArg] string $name, #[LiveArg] ?string $input = null): ?RedirectResponse
    {
        $this->confirmation?->set($input);
        if (!$this->allMatching) {
            // The browser says which records were chosen, so it could name any: only those the list could show go on.
            $this->selected = $this->visibleOf($this->selected);
        }
        if ($this->selected === [] && !$this->allMatching) {
            return null;
        }

        try {
            $result = $this->bulkActions->run($name, $this->selection(input: $this->confirmation?->get() ?? $input));
        } catch (CrudFailure $failure) {
            // What the dialog asked for is missing (ConfirmInput), a listener refused: nothing was done, the choice
            // stays.
            $this->dispatchBrowserEvent('toast:show', [
                'title' => $this->translator->trans('crud.toast.bulk_failed'),
                'description' => $this->failureMessage->of($failure),
            ]);

            return null;
        }
        $this->selected = [];
        $this->allMatching = false;
        if ($this->previewId !== null && $this->getPreviewed() === null) {
            $this->previewId = null;
        }

        return $result instanceof BulkResult ? $this->tell($result) : null;
    }

    /**
     * Does an action to one record on the server (a RunAction - see RecordActions::run()) and tells how it went.
     */
    #[LiveAction]
    public function run(
        #[LiveArg] string $name,
        #[LiveArg] string $id,
        #[LiveArg] ?string $input = null,
    ): ?RedirectResponse {
        $crud = $this->getDefinition();
        try {
            $result = $this->actions->run($crud, $name, $id, $input, RecordScreen::List);
        } catch (CrudFailure $failure) {
            $this->dispatchBrowserEvent('toast:show', [
                'title' => $this->translator->trans('crud.toast.action_failed'),
                'description' => $this->failureMessage->of($failure),
            ]);

            return null;
        }
        // The record may have left the list (a filter of its status), and the drawer with it.
        if ($this->previewId !== null && $this->getPreviewed() === null) {
            $this->previewId = null;
        }

        return $this->tell($result ?? new BulkResult($this->translator->trans('crud.toast.done')));
    }

    /**
     * What an action told: a toast, or the page it leads to (the toast waits for that page).
     */
    protected function tell(BulkResult $result): ?RedirectResponse
    {
        if ($result->redirect !== null) {
            // Elsewhere: the progress of a job sent to a queue, the file made. The toast waits for that page.
            $session = $this->requestStack?->getSession();
            if ($result->title !== '' && $session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('toast', $result->title);
            }

            return new RedirectResponse($result->redirect);
        }
        $this->dispatchBrowserEvent('toast:show', array_filter([
            'title' => $result->title,
            'description' => $result->description,
        ], is_string(...)));

        return null;
    }

    /**
     * A click on the heading of a column makes it the first thing the list is ordered by, ahead of the columns
     * chosen before: ascending when it is new, the way it was when it only moves up. A click on the column that
     * is the first already turns it round, and the one after that takes it out again. The order of the list form itself
     * is not one somebody chose, so the first column clicked takes its place - turned round when it is that very
     * column, or the click would change nothing.
     */
    #[LiveAction]
    public function sortBy(#[LiveArg] string $field): void
    {
        $this->stateChanged = true;
        if (!in_array($field, $this->getSortable(), true)) {
            return;
        }

        $sorted = $this->getSorted();
        $chosen = $this->chosenSorts($this->form($this->filters)) === [] ? [] : $sorted;
        $index = array_find_key($chosen, static fn (Sort $sort): bool => $sort->field === $field);

        if ($index === null) {
            $implied = array_find($sorted, static fn (Sort $sort): bool => $sort->field === $field);
            $first = $implied instanceof Sort && $chosen === [] ? $implied->reversed() : new Sort($field);
            array_unshift($chosen, $first);
        } elseif ($index > 0) {
            $moved = $chosen[$index];
            unset($chosen[$index]);
            array_unshift($chosen, $moved);
        } elseif (!$chosen[0]->isDescending()) {
            $chosen[0] = $chosen[0]->reversed();
        } else {
            array_shift($chosen);
        }

        $this->sort = $chosen === [] ? null : implode(',', array_map(strval(...), $chosen));
        $this->page = 1;
    }

    #[LiveAction]
    public function preview(#[LiveArg] string $id): void
    {
        $this->previewId = $id;

        // The click has already opened the drawer, showing placeholders until this answer arrives.
        if ($this->getPreviewed() === null) {
            $this->previewId = null;
            $this->dispatchBrowserEvent('crud-preview:close');
            $this->dispatchBrowserEvent('toast:show', ['title' => $this->translator->trans('crud.toast.gone')]);
        }
    }

    /**
     * Called when the drawer has been closed: the address should no longer lead to the record.
     */
    #[LiveAction]
    public function closePreview(): void
    {
        $this->previewId = null;
    }

    #[LiveAction]
    public function delete(
        #[LiveArg] string $id,
        #[LiveArg] ?string $input = null,
        #[LiveArg] string $name = DeleteAction::NAME,
    ): void {
        $crud = $this->getDefinition();

        try {
            // Only a delete the buttons offer the one asking: the browser can send any action.
            $action = $this->actions->deleteAction($crud, $name) ?? throw CrudFailure::forbidden();
            // A reason asked for (DeleteAction::$input), checked here: RecordDeleting and RecordDeleted carry it.
            $this->confirmation?->set($action->input?->accepted($input) ?? $input);
            $this->deletion->delete($crud, $id, RecordScreen::List);
        } catch (CrudFailure $failure) {
            $this->dispatchBrowserEvent('toast:show', [
                'title' => $this->translator->trans('crud.toast.delete_failed'),
                'description' => $this->failureMessage->of($failure),
            ]);

            return;
        }

        if ($this->previewId === $id) {
            $this->previewId = null;
        }
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->dispatchBrowserEvent('toast:show', [
            'title' => CrudToast::text($this->translator, $crud, 'deleted', ['id' => $id]),
        ]);
    }

    /**
     * The form of the list form, submitted with the given filters and what the list itself controls.
     *
     * @param array<string, mixed> $filters
     * @param int|null $page another page than the one in view
     * @param int|null $perPage another size of a page than the one in view
     *
     * @return FormInterface<mixed>
     */
    protected function form(array $filters, ?int $page = null, ?int $perPage = null): FormInterface
    {
        // What the list always has, whatever was typed or came with the request.
        $filters = array_replace($filters, $this->fixedFilters);
        $key = $this->formKey($filters, $page, $perPage);
        if (isset($this->forms[$key])) {
            return $this->forms[$key];
        }

        return $this->forms[$key] = $this->listQuery()->form(
            $this->getDefinition(),
            $filters,
            $this->sort,
            $page ?? $this->page,
            $perPage ?? $this->getPageSize(),
        );
    }

    /**
     * What a list form is submitted with, as a key: the filters, the page, its size and the order - all that can
     * change while a request is answered (a live action sets a filter, then the list is drawn).
     *
     * @param array<string, mixed> $filters
     */
    private function formKey(array $filters, ?int $page = null, ?int $perPage = null): string
    {
        return serialize([$this->resource, $filters, $page ?? $this->page, $perPage ?? $this->perPage, $this->sort]);
    }

    /**
     * What of the order in the address the list form knows; the rest (it may come from the URL) is left out, and
     * with nothing left the form's own order applies.
     *
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    private function chosenSorts(FormInterface $form): array
    {
        return $this->listQuery()->sorts($form, $this->sort);
    }

    /**
     * What the "sort" field of the list form accepts.
     *
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    private function sorts(FormInterface $form): array
    {
        return $this->listQuery()->sortable($form);
    }
}
