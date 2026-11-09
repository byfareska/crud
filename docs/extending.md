# Extending

The bundle is built from services behind interfaces. You can replace any of them by aliasing the interface to a
class of yours, or change part of one with `#[AsDecorator(...)]`.

## Services to replace or decorate

| Interface | Decides |
| --- | --- |
| `CrudReturn` | where a record's pages lead: "back", after save, after delete |
| `CrudAccess` | whether a resource, an operation or a page is available to the user (one extra rule everywhere: a licence, a tenant) |
| `RecordDeletion` | how a record is deleted (around it: an archive, a soft delete) |
| `FailureMessage` | the text of a refusal |
| `PageSizes` | page sizes and the default (e.g. the user's last choice) |
| `RecordTransaction` | what a save or delete runs in |
| `CrudRecords` | how a record is read for pages, the preview and guards |
| `CrudPages` | what happens when any page of a resource opens |
| `ListDefaults` | the filters and order a list starts with |
| `DoctrineEntityFactory`, `DoctrinePersister` | how the Doctrine driver creates, saves and deletes entities |
| `DoctrineSearch`, `DoctrineTextMatch`, `DoctrineFilterGuesser`, `DoctrineIdCodec`, `DoctrineExceptionMapper` | see [doctrine.md](doctrine.md) |
| `RelationChoiceLabel` | how a related entity is named |
| `RelationFormField`, `RelationFilterField` | whether relations of guessed forms and of filters are choices or fields to search in (automatic with `symfony/ux-autocomplete`) |
| `Actor`, `UserIdMapper`, `PanelAccount`, `IdMapper` | see [panels.md](panels.md) |
| `ThemeSelector` | which theme a page uses |
| `PaletteSearch`, `PaletteRanker` | see [command-palette.md](command-palette.md) |
| `DashboardLayouts`, `DashboardPolicy`, `DashboardKey`, `DashboardContext` | see [dashboards.md](dashboards.md) |
| `ChartTimezone`, `DayBucket` | see [dashboards.md](dashboards.md#charts) |

## Resources declared in code

Attribute arguments must be constants. Anything that depends on more goes through autoconfigured services:

| Service | |
| --- | --- |
| `CrudResourceProvider` | `resources()` yields `AsCrud` objects (one per report in a config file, those of enabled modules). Routes are built from them and cached: the class is watched in debug, but what it reads needs `cache:clear` |
| `CrudAdditionsProvider` | `pages()`, `formSections()`, `detailsSections()` |
| `CrudDefinitionExtension` | changes a resource for the current request |

```php
final readonly class TaskNumberForAdmins implements CrudDefinitionExtension
{
    public function __construct(private Actor $actor) {}

    public function resource(): string { return 'task'; }   // or CrudDefinitionExtension::EVERY

    public function extend(AsCrud $crud): AsCrud
    {
        return $this->actor->isGranted('ROLE_ADMIN')
            ? $crud->with(columns: [$crud->column('id'), ...$crud->columns])
            : $crud;
    }
}
```

- `with()` accepts the attribute's arguments except those routes are built from (`name`, `path`, `scope`, `own`,
  `listedIn`) and `role`; see `AsCrud::CHANGEABLE`.
- `$crud->refusing(CrudOperation::Delete)` takes an operation away for the request. A later extension can't give it
  back.
- The source can change only within the same class, entity, driver and operations:
  `$crud->with(source: $crud->source->with(filters: [...]))`.

`CrudRegistry::get()` and `all()` return resources as they are in the current request; `declared()` returns them as
declared.

## Live components of your own

`#[AsCrud(listComponent: 'App:ProductList')]` and `formComponent:` let you draw a resource's list or form with your own
live component, extending `CrudList` / `CrudForm` and using its template:

```php
#[AsLiveComponent('App:ProductList', template: CrudList::TEMPLATE)]
final class ProductList extends CrudList
{
    #[LiveAction]
    public function toggleActive(#[LiveArg] string $id, EntityManagerInterface $entities): void
    {
        $crud = $this->getDefinition();   // checks who may see the list
        // the guards ($this->guards), the record ($this->records), the change, a toast
    }
}
```

Don't override the constructor, because the bundle adds what it needs to it. Take services as arguments of live
actions, or through a `#[Required]` setter for what the template reads.

A component of your own that is drawn on a panel page should know its panel: implement `ScopedComponent`, or use
`DashboardWidgetProps` in a widget.
