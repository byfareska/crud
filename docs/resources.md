# Resources in depth

The arguments of `#[AsCrud]` that every driver understands.

## Identity and routing

| Argument | |
| --- | --- |
| `name` | `/^[a-z][a-z0-9_]*$/`; names translation keys and routes (`crud_<name>_list`). It can't be one of `AsCrud::RESERVED_NAMES` |
| `path` | `/products`; may have parameters: `/categories/{category}/products` (`requirements:` constrains them) |
| `scope` | the panel, `panel` by default |
| `idPattern` | what an id in the URL looks like (`\d+`), so `/products/export` can be a route of your own |
| `own: true` | a single record, that of the signed-in user (or what an `OwnRecordResolver` says) |

A resource whose path has parameters isn't in the menu or the palette. Link to it from a page that knows the
parameters: `new RouteAction('products', 'crud_category_product_list', 'box-seam', idParameter: 'category')`.

## The list

- `columns: ['name', 'price' => 'money']`: the type picks the Twig block a value is printed with. The bundle looks
  for, in order: `crud_product_price_list`, `field_price`, `type_money`, `value`. It searches the resource's `theme:`,
  then `byfareska_crud.value_themes`, then the panel theme's `crud/values.html.twig`.
- `title: ['firstName', 'lastName']`: what names a record (page titles, relations, the palette); default the first
  column.
- `perPage`, `defaultPerPage`, `filtersInView`, `listHead`, `listActions`: see the `AsCrud` docblock.
- `preview: false`: a click opens the details page instead of the drawer.
- `listComponent: 'App:ProductList'`: your own live component extending `CrudList`, for live actions on rows.

### Value formats

```php
columns: ['name', 'stock' => new ComponentFormat('App:StockLevel', ['low' => 5])],
```

```twig
{# templates/components/App/StockLevel.html.twig #}
{% props cell, low = 5 %}
<span{% if cell.raw < low %} class="text-error"{% endif %}>{{ cell.value }}</span>
```

`cell` is a `ValueView` with `value`, `raw`, `item`, `column`, `crud` and `view` (`list` or `detail`). To create a
format of your own, write a `ValueFormat` plus the `ValueFormatter` service it names.

`ValueNormalizer` services prepare values. Yours runs before the bundle's and can return
`new TypedValue('money', '12.50')`.

### A list on any page

```twig
{{ component('Crud:List', {
    resource: 'order_line',
    fixedFilters: {order: {comparison: 'eq', value: id}},
    urlPrefix: 'lines',     # ?lines_filters[...], ?lines_page=2: two lists on one page
}) }}
```

`fixedFilters` are always applied and never shown. The list keeps its state in the URL (`?filters[...]`, `/page/2`,
`?size=`, `?sort=`, `?preview=`); `inUrl: false` keeps it out. `ListDefaults` sets the initial filters and order, and
`ListStateChanged` is dispatched when the user changes them.

## Details and forms

- `details: [...]`: what the details page and the drawer show. Default: every property except secret-looking names.
  `hidden: [...]` hides properties whatever their name.
- `layout: [new Fieldset('basics', ['name', new Row(['sku', 'category'])]), new Tab(...)]`: tabs, fieldsets, rows
  and columns (`new Column(8, [...])`, `new Field('sku', cols: 4)`, `new Span(lg: 8)`).
  - `new When(PaidOrder::class, [...])` shows a part only when a `LayoutCondition` says so; fields left out this way
    aren't in the form at all.
  - `showIf: 'kind=company'` toggles a fieldset or tab in the browser.
- `save: AfterSave::Show` decides where "save" leads; `afterSave: [AfterSave::Return, AfterSave::New]` adds more
  buttons. A `CrudSaveButton` service is a button of your own.
- `formComponent: 'App:InvoiceForm'`: your own live component extending `CrudForm`.
- `#[AsCrudFormSection]` / `#[AsCrudDetailsSection]` let another module add a section, saved in the same transaction.
- `CrudFormExtension` changes the form for the request: disable a field for a role, or remove it.

## Actions of a record

Every record gets details, edit and delete. Add more with `actions:`:

| Action | |
| --- | --- |
| `new DuplicateAction()` | the add form prefilled from the record (`RecordCopying` adjusts the copy) |
| `new RouteAction('invoices', 'app_customer_invoices', 'receipt', fieldParameters: ['customer' => 'customerId'])` | a link |
| `new ComponentAction('restock', 'App:Restock', 'box', fields: ['stock'])` | a Twig component draws it |
| `new LiveListAction('toggle', 'toggleActive', 'toggle-on')` | a live action of your own list component |
| `new RunAction('approve', 'check2', ApproveInvoice::class, message: '...')` | done on the server, in a transaction, with guards and events |
| `new NoAction('details')` | removes a built-in action |

- `inline: true` puts an action on the row instead of in its menu.
- `priority:` orders actions.
- `role:` restricts who sees an action.
- `tone: ActionTone::Info` colours it.
- Its label is `crud.<resource>.action.<name>`.

A `RecordActionGuard` decides which records an action is for. A kind of action of your own is a `RecordAction` plus
its `RecordActionHandler`. `MakesButtons` gives one action several buttons, for example workflow transitions.

## Bulk actions

```php
bulkActions: [new BulkDeleteAction()],
```

When the user selects a whole page, they're offered "select all N matching". The action then receives a
`BulkSelection` with `allMatching`, `count`, lazily-read `ids`, `each()` for streaming, and the list's `filters` and
`sort`, so a queued job can read the same records again with `CrudListQuery`.

A bulk action of your own is a `BulkAction` plus its `BulkActionHandler` (`button()`, `run()` → `BulkResult`).

`new ConfirmInput('app.reason', type: 'line', choices: [...])` asks for input before running. The server checks the
input again before acting.

## Pages of your own

```php
#[AsCrudPage(resource: 'product', name: 'stats', component: 'App:ProductStats', icon: 'graph-up')]
#[AsTwigComponent('App:ProductStats', template: 'components/App/ProductStats.html.twig')]
final class ProductStats
{
    public string $id = '';
}
```

- The route is `crud_product_stats` at `/products/{id}/stats`, with links from the record.
- `collection: true` makes a page of the whole resource (`/products/stats`).
- `resource: AsCrudPage::EVERY` adds it to every resource.

For a controller of your own, use `#[MapCrud]`:

```php
#[Route('/categories/{id}/summary', name: 'app_category_summary')]
public function summary(#[MapCrud('category')] CrudContext $category): Response
```

Render it with `{% extends '@CrudTheme/crud/record.html.twig' %}`. Use `#[MapCrud('invoice', update: true, action:
'approve')]` for controllers that change the record.

## Access

```php
#[AsCrud(name: 'task', role: 'ROLE_STAFF', roles: ['delete' => 'ROLE_ADMIN'])]
```

- `role` decides who may see the resource at all.
- `roles` restricts operations (`list`, `read`, `create`, `update`, `delete`) further.

Both are checked with the `AsCrud` as the subject, so a voter of yours can decide them. The bundle checks access on
every way to a resource: pages, live components, the relation searches, the menu and the palette.

If you protect the panel with `access_control` or a firewall on its prefix instead, that works too: every address of
the panel is under its prefix, including its live components (`_crud/...`) and the relation searches
(`_crud/autocomplete/...`).

For individual records:

- `RecordGuard` (`allows($operation, $record)`) refuses an operation on a record.
- `ExplainsRefusal` shows a disabled button with a reason instead of hiding it.
- `VoterRecordGuard` and `VoterActionGuard` delegate to your voters.
- `DoctrineScope` decides which records exist at all.
- `RecordRow` adds classes or attributes to rows.

## Events and transactions

| Event | Has |
| --- | --- |
| `RecordSaving` / `RecordSaved` | `crud`, `id`, `data`, `before`; `RecordSaved` also `created` |
| `RecordDeleting` / `RecordDeleted` | the record, `input` |
| `RecordChanging` / `RecordChanged` | changes other than the form (`RunAction`, `RecordChanges::change()`) |
| `BulkActionRunning` / `BulkActionDone` | `action`, `selection`, `result` |

A save or a delete is all or nothing: the events, the driver and form sections run in one transaction (the Doctrine
connection, `RecordTransaction`). A listener refuses by throwing a `CrudFailure`.

Don't send e-mails or messages from `RecordSaved` itself. Dispatch them through a transport written in the same
transaction, or do it on `kernel.terminate`. `options: ['transaction' => false]` turns the transaction off for a
resource.
