<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Action\RecordActions;
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Driver\ListsIds;
use Byfareska\Crud\UseCase\ListPage;
use Byfareska\Crud\UseCase\Page;
use Generator;
use Symfony\Component\Form\FormInterface;

/**
 * What a list of a resource shows for its filters and order, outside the list: the records of an export (a CSV of
 * every match, streamed), of a message handled later (the bulk action "all N matching" sent to Messenger), of a report.
 * The same driver, scopes, search and filters as the live list (CrudList, which goes through it), so what is
 * exported is what was seen.
 *
 * The filters are those of the list's address ("filters"), the order as in its "sort" ("-createdAt,name"). Who may
 * see the list is checked (CrudAccess): a message handled without a user checks nothing it could not.
 */
final readonly class CrudListQuery
{
    /** The fields of the list form the list drives; every other one is a filter. */
    public const array CONTROLLED = ['page', 'perPage', 'fields', self::SORT];

    public const string SORT = 'sort';

    public function __construct(
        private CrudDrivers $drivers,
        private CrudAccess $access,
        private PageSizes $pageSizes,
        private RecordActions $actions,
        private RecordGuards $guards,
        private RecordRows $rows,
        private IdMapper $idMapper,
    ) {
    }

    /**
     * The list form of the resource submitted with the filters and what the list drives: the page, its size, the
     * fields an item carries (the columns, "id", AsCrud::$fields and what the actions, guards and rows need) and the
     * order - of it only what the form knows.
     *
     * @param array<string, mixed> $filters
     *
     * @return FormInterface<mixed>
     */
    public function form(
        AsCrud $crud,
        array $filters = [],
        ?string $sort = null,
        int $page = 1,
        ?int $perPage = null,
    ): FormInterface {
        $this->access->assertGranted($crud, CrudOperation::List);
        $form = $this->drivers->of($crud)->listForm($crud);

        $controlled = [
            'page' => (string) max(1, $page),
            'perPage' => (string) ($perPage ?? $this->pageSizes->defaultOf($crud)),
            // The id is what the row actions address a record by.
            'fields' => $this->fields($crud),
        ];
        $sorts = $this->sorts($form, $sort);
        if ($sorts !== []) {
            $controlled[self::SORT] = $sorts;
        }

        $data = [];
        foreach (array_keys($form->all()) as $name) {
            if (array_key_exists($name, $controlled)) {
                $data[$name] = $controlled[$name];
            } elseif (array_key_exists($name, $filters)) {
                $data[$name] = $filters[$name];
            }
        }

        return $form->submit($data);
    }

    /**
     * One page of the list. A list form without "page" (its use case gives every record) is paged here.
     *
     * @param array<string, mixed> $filters
     *
     * @throws CrudFailure
     */
    public function page(
        AsCrud $crud,
        array $filters = [],
        ?string $sort = null,
        int $page = 1,
        ?int $perPage = null,
    ): Page {
        $perPage ??= $this->pageSizes->defaultOf($crud);

        return $this->pageOf($crud, $this->form($crud, $filters, $sort, $page, $perPage), $page, $perPage);
    }

    /**
     * A page the submitted form asks for, paged here when the form has no "page".
     *
     * @param FormInterface<mixed> $form
     *
     * @throws CrudFailure
     */
    public function pageOf(AsCrud $crud, FormInterface $form, int $page, int $perPage): Page
    {
        $result = $this->drivers->of($crud)->list($crud, $form);
        if ($form->has('page')) {
            return $result;
        }
        $last = max(1, (int) ceil($result->total / max(1, $perPage)));
        $page = min(max(1, $page), $last);

        return new ListPage(
            array_slice($result->items, ($page - 1) * $perPage, $perPage),
            $result->total,
            $page,
            $perPage,
        );
    }

    /**
     * Every item the filters match, in the order of the list, read page by page as they are gone through - not all at
     * once: an export of a hundred thousand rows holds one page. Filters the list does not accept match nothing.
     *
     * @param array<string, mixed> $filters
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @throws CrudFailure
     */
    public function items(AsCrud $crud, array $filters = [], ?string $sort = null, ?int $perPage = null): Generator
    {
        $perPage ??= max($this->pageSizes->of($crud));
        $page = 1;
        $seen = 0;
        do {
            $form = $this->form($crud, $filters, $sort, $page, $perPage);
            if (!$form->isValid()) {
                return;
            }
            $result = $this->drivers->of($crud)->list($crud, $form);
            foreach ($result->items as $item) {
                ++$seen;
                yield $item;
            }
            // A list form without "page" gives every record at once.
            ++$page;
        } while ($form->has('page') && $result->items !== [] && $seen < $result->total);
    }

    /**
     * The ids of every record the filters match, in the order of the list, each once: by one query when the driver
     * can tell them so (ListsIds), else read page by page. Given as they come, not gathered first.
     *
     * @param array<string, mixed> $filters
     *
     * @return Generator<int, string>
     *
     * @throws CrudFailure
     */
    public function ids(AsCrud $crud, array $filters = [], ?string $sort = null): Generator
    {
        $seen = [];
        $driver = $this->drivers->of($crud);
        $ids = null;
        if ($driver instanceof ListsIds) {
            $form = $this->form($crud, $filters, $sort, 1, max($this->pageSizes->of($crud)));
            if (!$form->isValid()) {
                return;
            }
            $ids = $driver->ids($crud, $form);
        }
        $ids ??= (function () use ($crud, $filters, $sort): Generator {
            foreach ($this->items($crud, $filters, $sort) as $item) {
                yield $this->idOf($item);
            }
        })();

        foreach ($ids as $id) {
            // By id, so a record a page shows again is one: array keys would make "12" a number.
            if ($id !== '' && !isset($seen[$id])) {
                $seen[$id] = true;
                yield $id;
            }
        }
    }

    /**
     * The ids of every record the filters match that its guards let be read (RecordGuard, Read) - what "every match" of
     * a bulk action is: the same records the list shows. Without guards ids(); with them the items of the list, which
     * carry what the guards need (fields()), so no record is read again by itself.
     *
     * @param array<string, mixed> $filters
     *
     * @return Generator<int, string>
     *
     * @throws CrudFailure
     */
    public function readableIds(AsCrud $crud, array $filters = [], ?string $sort = null): Generator
    {
        if (!$crud->can(CrudOperation::Read) || !$this->guards->guards($crud)) {
            yield from $this->ids($crud, $filters, $sort);

            return;
        }

        $seen = [];
        foreach ($this->items($crud, $filters, $sort) as $item) {
            $id = $this->idOf($item);
            if (
                $id !== ''
                && !isset($seen[$id])
                && $this->guards->allows(CrudOperation::Read, new RecordView($crud, $id, $item, RecordScreen::List))
            ) {
                $seen[$id] = true;
                yield $id;
            }
        }
    }

    /**
     * Whether readableIds() can be gone through while what they name is changed: one query of the ids alone, made
     * before the first is given (ListsIds), and no guards to read the items for. Otherwise they are read page by page,
     * and a change that takes a record out of the filters moves the pages under the rest - gather them first.
     */
    public function streams(AsCrud $crud): bool
    {
        return $this->drivers->of($crud) instanceof ListsIds
            && (!$crud->can(CrudOperation::Read) || !$this->guards->guards($crud));
    }

    /**
     * The id of an item of the list, as in an address.
     *
     * @param array<string, mixed> $item
     */
    public function idOf(array $item): string
    {
        return $this->idMapper->toId($item['id'] ?? null) ?? '';
    }

    /**
     * What an item of the list carries.
     *
     * @return list<string>
     */
    public function fields(AsCrud $crud): array
    {
        return array_values(array_unique([
            'id',
            ...array_map(static fn (Column $column): string => $column->field, $crud->columns),
            ...$crud->fields,
            ...$this->actions->fields($crud),
            ...$this->guards->fields($crud),
            ...$this->rows->fields($crud),
        ]));
    }

    /**
     * What of the order asked for the list form knows; the rest (it may come from the URL) is left out, and with
     * nothing left the form's own order applies.
     *
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    public function sorts(FormInterface $form, ?string $sort): array
    {
        return array_values(array_intersect(explode(',', $sort ?? ''), $this->sortable($form)));
    }

    /**
     * What the "sort" field of the list form accepts.
     *
     * @param FormInterface<mixed> $form
     *
     * @return list<string>
     */
    public function sortable(FormInterface $form): array
    {
        $choices = $form->has(self::SORT) ? $form->get(self::SORT)->getConfig()->getOption('choices') : null;

        return is_array($choices) ? array_values(array_filter($choices, is_string(...))) : [];
    }
}
