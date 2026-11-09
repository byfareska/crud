# Doctrine resources

```php
#[ORM\Entity]
#[AsCrud(
    name: 'category', path: '/categories', icon: 'tags',
    source: new DoctrineSource(self::class, search: ['name'], sort: ['name']),
    columns: ['name', 'parent', 'createdAt'],
    section: 'Catalog',
)]
class Category { /* ... */ }
```

You can put the attribute on the entity or on a class of its own (`App\Crud\CategoryCrud`), which keeps the entity
free of panel concerns. The example application has a minimal resource
([`src/Crud/Doctrine/Minimal`](https://github.com/byfareska/crud-example/tree/main/src/Crud/Doctrine/Minimal)) and one
that uses every feature ([`src/Crud/Doctrine/Maximal`](https://github.com/byfareska/crud-example/tree/main/src/Crud/Doctrine/Maximal)).

## `DoctrineSource`

| Argument | |
| --- | --- |
| `entity` | the class |
| `form` | a form type, or a `DoctrineForm` (a type with options, or a list of guessed fields); null = guessed from the mapping |
| `createForm`, `updateForm` | replace `form` for adding or for editing only |
| `fields` | what the guessed form has; empty = see "Guessed forms" |
| `filters` | `['active', 'category' => EntityType::class, 'priceFrom' => new PriceBound('price')]` |
| `search` | fields the search box looks in, also through relations (`'category.name'`) |
| `searchWith` | a `DoctrineSearch` service of your own (full-text, an exact SKU first) |
| `sortable` | what column headings sort by; empty = every mapped column |
| `sort` | the default order, `['-createdAt']` |
| `operations` | `CrudOperation` cases, e.g. `[CrudOperation::List, CrudOperation::Read]` for a read-only resource |
| `countLimit` | count at most N records ("more than N"), for tables where `COUNT` is slow |
| `countWith` | a `DoctrineCount` service used instead of `COUNT` |
| `relations` | which resource offers the entities of a relation, when an entity has several resources |
| `lock` | `SELECT ... FOR UPDATE` on records read inside a transaction (deletes, `RecordChanges`) |
| `manager` | the entity manager; related entities are read from it too |

## Guessed forms

Without a form type, the form is built from the mapping:

- **Fields:** every field and owning-side relation, except the id, the `#[Version]` field, columns the database fills
  (generated, not insertable or updatable) and names that look secret (`passwordHash`, `apiKey`; see
  `AsCrud::SECRET`).
- **Read-only properties** (no setter, not public) are left out of the edit form. In the add form they are included
  only if they are constructor arguments. A `createdAt` set in the constructor appears in neither.
- **Constructors:** a new entity is created with its constructor, which receives the submitted values of the fields
  with the same names as its parameters (`new Product(string $name, Category $category)`). If a required parameter has
  no field, the form fails when it is built and the error names the parameter.
- **Relations** are choices of the related entity, labelled as in the list (see "Relations" below).

When the add form and the edit form must differ:

```php
new DoctrineSource(
    User::class,
    createForm: new DoctrineForm(fields: ['email', 'plainPassword', 'roles']),
    updateForm: new DoctrineForm(UserType::class, ['validation_groups' => ['Default', 'edit']]),
)
```

To create entities differently everywhere (a named constructor, a domain factory), alias `DoctrineEntityFactory`. To
save them differently (`$order->cancel()` instead of a delete), alias `DoctrinePersister`.

## Relations

A related entity is shown, in the list, the details and the choices of forms and filters, by the first of these
that applies:

1. a `RelationChoiceLabel` service of yours (`"Kowalska, Anna (PL123)"`);
2. the title of the related entity's resource: its `title:` argument, else its first column;
3. its `__toString()`;
4. its id.

The value of a relation in a list item or a record is a `TypedValue('relation', RecordRef)`. As text it is the
name above; in JSON (live props, URLs, APIs) it is the id. Print it differently with a `type_relation` block in your
`value_themes`.

A relation to the same entity (a category's parent) never offers the record being edited.

### Choices or a field to search in

What a relation of a guessed form is depends on the `RelationFormField` service:

| | |
| --- | --- |
| `ChoiceRelationFormField` (without `symfony/ux-autocomplete`) | a `<select>` of every related entity |
| `AutocompleteRelationFormField` (with `symfony/ux-autocomplete`, chosen automatically) | a field to search in (Tom Select): only the chosen entity is loaded, the others are found as you type |

The search goes through the bundle's own autocompleter (`byfareska_crud_relation`), not the default one of UX
Autocomplete:

- it searches the `search:` fields of the related resource, else its text fields;
- it stays inside that resource's `DoctrineScope`s;
- it answers only users who may add or edit records of the resource;
- it names entities as described above;
- it leaves out the record being edited.

Its options are signed by UX Autocomplete, so a request can't ask for another resource or field.

The searches go to the panel's own address, `<prefix>/_crud/autocomplete/{alias}` (route `crud_autocomplete_<panel>`),
not to the global `/autocomplete/{alias}` of UX Autocomplete. That address is under the panel's prefix, so the panel's
firewall and `access_control` cover it like the panel's pages. The bundle's autocompleter refuses requests that come
through the global address, or through another panel's address. You don't need to protect `/autocomplete` for the
panel's sake.

Relation filters work the same way through `RelationFilterField`. To keep plain choices everywhere:

```yaml
# config/services.yaml
services:
    Byfareska\Crud\Driver\Doctrine\RelationFormField: '@Byfareska\Crud\Driver\Doctrine\ChoiceRelationFormField'
```

## Filters

A field in `filters` gets a small set of comparisons, chosen in the browser:

| Field | Compared by |
| --- | --- |
| date, datetime | between, on, from, until (by the day; by the minute with `DateTimeType`) |
| number | =, ≠, >, ≥, <, ≤, between |
| text | contains, does not contain, is, is not, starts with, ends with |
| relation, enum | is, is not (collections: has, has not); typed search with `symfony/ux-autocomplete` |
| boolean | yes / no |

```php
filters: [
    'createdAt' => new FieldFilter('createdAt', [Comparison::AtLeast]),
    'categoryName' => new FieldFilter('category.name', Comparison::TEXT),
    'owner' => new FieldFilter('owner', Comparison::CHOICES),   // + "is one of", "is empty"
],
```

`#[AsCrud(filtersInView: ['category'])]` keeps filters visible; the rest are under "More filters".

To add a comparison of your own ("in the last N days"), implement `DoctrineComparison`. To add a whole filter of your
own, write a `DoctrineFilter` plus the `DoctrineFilterHandler` service it names. Text comparison uses
`LOWER()` on both sides; alias `DoctrineTextMatch` for `unaccent()` or a case-insensitive collation.

## Search and sorting

The default `WordsSearch` requires every typed word to appear in one of the `search` fields
(`LOWER(...) LIKE`). It works on any database but uses no index. For something else, implement `DoctrineSearch`
and either alias it globally or name it in `searchWith:`.

A custom order is a `DoctrineSort` in `sortable` plus its `DoctrineSortHandler` service; select an expression as
`HIDDEN` first.

## Narrowing and extending the query

| Service | |
| --- | --- |
| `DoctrineScope` | which entities exist for the panel at all ("only mine"); applies to the list, records found by id, relation filters |
| `DoctrineListExtension` | changes only the list query, after scopes, search, filters and order (joins, computed columns) |
| `DoctrineItems` | adds data from elsewhere to a whole page of items at once |

The query builder has the entity as `e`. `$query->path('category.name')` joins a relation once and
`$query->parameter()` gives a fresh parameter name.

Pages are counted by distinct entities, so joining a collection doesn't shrink a page. `perPage` caps what a URL can
ask for.

## Ids

`DoctrineIdCodec` turns an id into its URL form: as it is for a single field (`7`, a UUID), and the parts joined
by `,` for composite ids (`12,3`). Alias it for hashed ids or slugs.

## Errors from the database

Instead of a 500, these become form or delete errors (`DoctrineExceptionMapper`, which you can decorate):

| | Key | Status |
| --- | --- | --- |
| unique key | `crud.error.taken` | 409 |
| foreign key | `crud.error.in_use` | 409 |
| deadlock, lock timeout | `crud.error.retry` | 503 |
| other constraint | `crud.error.refused` | 422 |

The foreign-key mapping relies on the database enforcing foreign keys. SQLite doesn't by default, see
[troubleshooting](troubleshooting.md#sqlite-deletes-records-that-are-still-in-use).

An entity with `#[ORM\Version]` is locked optimistically: saving over someone else's newer save is refused
(`crud.error.changed`). If a failed flush closes the entity manager, a new one is created.
