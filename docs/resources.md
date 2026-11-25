# Resources in depth

The arguments of `#[AsCrud]` that every driver understands.

## Identity and routing

| Argument | |
| --- | --- |
| `name` | `/^[a-z][a-z0-9_]*$/`; names translation keys and routes (`crud_<name>_list`). It can't be one of `AsCrud::RESERVED_NAMES` |
| `path` | `/products`; may have parameters: `/categories/{category}/products` (`requirements:` constrains them) |
| `scope` | the panel, `panel` by default |
| `idPattern` | what an id in the URL looks like (`\d+`), so `/products/export` can be a route of your own |
| `own: true` | a single record, that of the signed-in user (or what an `OwnRecordResolver` says); its guessed form has nothing that looks privileged (see [What a form changes](#what-a-form-changes)) |

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

An enum that implements `TranslatableInterface` is printed by its `trans()`, in the locale of the request, in the list,
the details and the title of a record. Its value in a list item or a record is a
`TypedValue('enum', TranslatedValue)`: as text the translation, in JSON (live props, URLs, APIs) the enum's value.
Print it differently with a `type_enum` block in your `value_themes`. Any other enum is its value (or name).
A Doctrine field with `enumType` holds its enum here too, also a list of them (`simple_array`, `json`).

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
- `hidden: [...]`: properties nobody sees, whatever else names them. See [Hidden properties](#hidden-properties).
- `layout: [new Fieldset('basics', ['name', new Row(['sku', 'category'])]), new Tab(...)]`: tabs, fieldsets, rows
  and columns (`new Column(8, [...])`, `new Field('sku', cols: 4)`, `new Span(lg: 8)`).
  - `new When(PaidOrder::class, [...])` shows a part only when a `LayoutCondition` says so; fields left out this way
    aren't in the form at all. A `When` only arranges the forms and the details: the list, its search, sorting and
    filters and the palette still have the property. To keep a property from someone, hide it (below).
  - `showIf: 'kind=company'` toggles a fieldset or tab in the browser.
- `save: AfterSave::Show` decides where "save" leads; `afterSave: [AfterSave::Return, AfterSave::New]` adds more
  buttons. A `CrudSaveButton` service is a button of your own.
- `formComponent: 'App:InvoiceForm'`: your own live component extending `CrudForm`.
- `#[AsCrudFormSection]` / `#[AsCrudDetailsSection]` let another module add a section, saved in the same transaction.
- `CrudFormExtension` changes the form for the request: disable a field for a role, or remove it.

### Hidden properties

`hidden: ['costPrice']` keeps a property from everyone. A hidden property is:

- not a column of the list, a row of the details or the drawer, or part of a record's `title`;
- not a field of a guessed form (Doctrine), and never prefilled by `?with[...]=` or copied by `?from=` (duplicate);
- not searched, sorted or filtered by, even when the source names it in `search`, `sortable` or `filters`. A sort
  asked for it (`?sort=-costPrice`) is ignored. The default order (`sort:`) may still use it.
- not named or described in the command palette, nor found by it.

A record still carries it, for guards, actions and `fields`. A form type of your own, or the fields you list in a
`DoctrineForm`, is yours: a hidden property you put there is still edited (only `?with`/`?from=` don't fill it). Take
it out of the form with a `CrudFormExtension`. The `dto` driver's forms are always your own.

To hide a property from some users only, hide it in a `CrudDefinitionExtension` for the request. `hiding()` adds to
what is hidden, the declared `hidden` included:

```php
public function extend(AsCrud $crud): AsCrud
{
    return $this->actor->isGranted('ROLE_FINANCE') ? $crud : $crud->hiding('margin');
}
```

The other way round is safer: declare it hidden (`hidden: ['margin']`) and show it to the few who may see it, with
`showing()`. Then a request your extension doesn't recognise shows it to nobody:

```php
public function extend(AsCrud $crud): AsCrud
{
    return $this->actor->isGranted('ROLE_FINANCE') ? $crud->showing('margin') : $crud;
}
```

`showing()` shows only the properties it names. `with(hidden: [...])` replaces the whole list: whatever it leaves out is
shown again, the declared `hidden` too.

Removing a column with `with(columns: ...)` only changes the list. The details, the form, search, sorting and filters
still have the property.

## Actions of a record

Every record gets details, edit and delete. Add more with `actions:`:

| Action | |
| --- | --- |
| `new DuplicateAction()` | the add form prefilled from the record (`RecordCopying` adjusts the copy); relations only within the current `DoctrineScope`s |
| `new RouteAction('invoices', 'app_customer_invoices', 'receipt', fieldParameters: ['customer' => 'customerId'])` | a link |
| `new ComponentAction('restock', 'App:Restock', 'box', fields: ['stock'])` | a Twig component draws it |
| `new LiveListAction('toggle', 'toggleActive', 'toggle-on')` | a live action of your own list component |
| `new RunAction('approve', 'check2', ApproveInvoice::class, message: '...')` | done on the server, in a transaction, with guards and events |
| `new NoAction('details')` | removes a built-in action |

- `inline: true` puts an action on the row instead of in its menu.
- `priority:` orders actions.
- `role:` restricts who sees an action, and who may do it. It takes away only that action (the `role` of a
  `DeleteAction` also takes away a `BulkDeleteAction` without a role of its own): to restrict an operation, use
  `roles` (see [How the restrictions add up](#how-the-restrictions-add-up)).
- `?from=<id>` copies a record into the add form only for a user offered the `DuplicateAction` for that record (its
  `role`, a `RecordActionGuard` of `duplicate`, the guards of reading it). Without a `DuplicateAction` the address
  answers 403.
- A `RunAction` changes what may be edited (`update: true`, the default): the `RecordGuard`s of `update` are asked
  too, and it is not offered to a user whose `roles` (or a `refusing()`) take `update` away. Give `update: false` to
  an action people who may not edit may do.
- `tone: ActionTone::Info` colours it.
- Its label is `crud.<resource>.action.<name>`.

A `RecordActionGuard` decides which records an action is for. Details and edit go by their `RecordGuard`s only.
Deleting asks the guards of `delete` too, whatever your `DeleteAction` is called: a record they refuse has no delete
button and isn't deleted, on its own or in bulk. A kind of action of your own is a `RecordAction` plus its
`RecordActionHandler`. `MakesButtons` gives one action several buttons, for example workflow transitions.

## Bulk actions

```php
bulkActions: [new BulkDeleteAction()],
```

`BulkDeleteAction` deletes each record the way a record is deleted on its own: the `RecordGuard`s of `delete`, the
`RecordActionGuard`s of the action `delete` and `RecordDeleting` listeners. A record they refuse stays, the others are
deleted, and the toast counts the ones that couldn't be. Without a `role` of its own, it is offered only to users
offered a `DeleteAction` of the resource: `new DeleteAction(role: 'ROLE_ADMIN')` restricts deleting in bulk too, and
asking for it anyway does nothing. A resource with `new NoAction('delete')` deletes in bulk only, for everybody the
resource is for (give the `BulkDeleteAction` a `role` to restrict it).

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
(`_crud/autocomplete/...`), and the bundle answers them nowhere else - a list, a form or a widget of the panel asked
for under the prefix of another panel or at `/_components/...` is refused (see [panels](panels.md#panels)). What
`access_control` doesn't see:

- the pages of your own controllers (`#[MapCrud]`) outside the prefix: protect their routes yourself;
- live components of yours that don't implement `ScopedComponent`: they are served under any prefix and at
  `/_components/...`, so they check access themselves;
- with `prefix: /{shop}/admin`, `access_control` sees the path, not who may see which shop: check that in a voter, a
  listener on the route attribute or a `DoctrineScope`.

For individual records:

- `RecordGuard` (`allows($operation, $record)`) refuses an operation on a record.
- `ExplainsRefusal` shows a disabled button with a reason instead of hiding it.
- `VoterRecordGuard` and `VoterActionGuard` delegate to your voters (`DELETE` is asked by `VoterRecordGuard` only).
- `DoctrineScope` decides which records exist at all.
- `RecordRow` adds classes or attributes to rows. The theme escapes their values (`crud_attrs()`) and leaves out a name
  that is no name of an attribute (a space, a quote, `=`, `/` or `>` in it), so names may be made of data too.

### How the restrictions add up

Roles and refusals only narrow. The panel's role, `role` and the role of the operation in `roles` all have to
allow it. A `CrudDefinitionExtension` can't change `role` or `roles`, and what one extension refuses with `refusing()`, a
later one can't give back: the bundle throws a `LogicException`.

An action's `role` and its `RecordActionGuard` take away that action only. `new DeleteAction(role: 'ROLE_ADMIN')`
removes the delete button of a row and, from a `BulkDeleteAction` without a role of its own, deleting in bulk; a
`BulkDeleteAction` with a role keeps its own. But an extension that returns `$crud->with(actions: [...])` without the
declared actions brings back the built-in delete, which has no role, for both. To keep an operation from someone,
use `roles: ['delete' => 'ROLE_ADMIN']` or `refusing()`: every way to it asks that.

`with()` replaces the value you give it. `with(hidden: ['margin'])` shows again what was hidden before, the declared
`hidden` too, and `with(actions: [...])` drops the declared actions. Hide with `hiding('margin')` and show with
`showing('margin')`: they change only the properties they name. Add to the current value of the rest:
`with(actions: [...$crud->actions, $export])`.

### What a form changes

Guards and scopes are asked of the record as it is stored, before the form changes it. The saved record isn't
checked again, and `RecordGuard`s are never asked about a new one: who may add records is `roles['create']`. If the
form can change what a guard or a scope goes by (an owner, a tenant), a user who may edit their own customer can give
it to someone else. A guessed Doctrine form has every relation it can write, offering the entities in the
`DoctrineScope`s of the related resource. Hide such a field, disable it in a `CrudFormExtension`, or refuse the change
in a `RecordSaving` listener, which runs in the transaction of the save:

```php
#[AsEventListener]
public function __invoke(RecordSaving $event): void
{
    if ($event->data instanceof Customer && $event->data->owner !== $this->currentUser()) {
        throw CrudFailure::forbidden();
    }
}
```

**A guessed form edits every property it can write**, also the ones that say what someone may do or is vouched for:
`roles`, `isAdmin`, `isVerified`, `enabled`, `tenant`, `owner`, `hourlyRate`, `balance`. On a `User` resource for
your staff, whoever may edit a user, themselves included, can give that user `ROLE_ADMIN` or another rate. Name the
fields (`fields:` of the source), hide them or disable them in a `CrudFormExtension` for those who may not set
them, and refuse a change you can't allow in a `RecordSaving` listener.

The guessed form of an `own` resource ("my account", "my profile") is safer by default: it leaves out every property
whose name has a word of `AsCrud::PRIVILEGED` (`roles`, `admin`, `verified`, `enabled`, `active`, `tenant`, `team`,
`owner`, `rate`, `balance`, `plan`...) and every relation to a Symfony Security user. These are names, not meanings:
`kycStatus` or `level` stays. List the `fields` of an own resource instead of relying on the guess. An `email` stays
too. If users sign in with it, or something trusts it because it was verified, change it in a flow of your own that
verifies the new address.

### Related records

A relation of a guessed form or a relation filter offers the entities in the `DoctrineScope`s of the related
resource. Its `role`, `roles` and `RecordGuard`s aren't asked: whoever may fill in the form sees the names of those
entities and may choose them. Hide or disable the field for users who shouldn't.

A relation in a list cell or in the details shows the name of the entity the record holds, never a link. A to-many
relation you name in `columns` or `details` (the `orders` of a customer) lists every entity of the collection, not
narrowed by the scopes, role or guards of the related resource. To show related records as the user may see them,
embed the related resource's list with `fixedFilters` ([A list on any page](#a-list-on-any-page)): it keeps to that
resource's access, scopes and guards.

The components of `#[AsCrudDetailsSection]` and the DTOs of `#[AsCrudFormSection]` are yours. Their `role` is asked
with the resource as the subject, and they get the id of a record the user may read (details) or edit (edit form).
Records of another resource they show or change, they check themselves.

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
