# DTO resources and drivers of your own

## The `dto` driver

Every operation is a DTO that names its use case. The panel only runs them, so the same DTOs can serve an API or a
console command, and the records can be stored anywhere.

```php
#[AsCrud(
    name: 'task', path: '/tasks', icon: 'check2-square',
    list: ListTasksDto::class, read: ShowTaskDto::class,
    create: CreateTaskDto::class, update: UpdateTaskDto::class, delete: DeleteTaskDto::class,
    columns: ['title', 'priority', 'done', 'createdAt'],
    search: 'query',
)]
final class TaskCrud {}

#[AsFormType(options: ['method' => 'GET', 'csrf_protection' => false])]
#[UseCase(ListTasks::class)]
final class ListTasksDto implements MapsToRequest
{
    #[FormField(SearchType::class, ['required' => false, 'empty_data' => ''])]
    public string $query = '';

    #[FormField(ChoiceType::class, ['choices' => self::SORTS, 'multiple' => true, 'required' => false])]
    public array $sort = ['-createdAt'];

    #[FormField(IntegerType::class, ['required' => false, 'empty_data' => '1'])]
    public int $page = 1;

    #[FormField(IntegerType::class, ['required' => false, 'empty_data' => '25'])]
    public int $perPage = 25;

    public function toRequest(): ListTasksRequest { /* ... */ }
}

final readonly class ListTasks implements Query   // or Command; both are invokable services
{
    public function __invoke(ListTasksRequest $request): ListPage { /* ... */ }
}
```

| Operation | DTO |
| --- | --- |
| `list` | a form type DTO. The list drives `page`, `perPage`, `sort` and `fields`; every other field is a filter. Returns a `Page` (`ListPage`) of arrays with an `id`, or a plain list that the panel pages itself |
| `read`, `delete` | a public `id`. `read` returns an object whose public properties and getters are the record |
| `create`, `update` | form type DTOs. `update` has a public `id` and starts with the values `read` returned, property by property; `FillsFromRecord::fillFrom()` handles anything structured |

- A use case that returns an id, or something with an `id`, after `create` sends the user to the new record.
- `#[UseCase(errors: [TaskNotFound::class => new Failure(404, message: 'crud.error.not_found')])]` maps exceptions;
  a `UseCaseErrorMapper` does it for every use case.
- A `ValidationFailedException` from the domain shows each message at its field.
- A `ValueError` from `toRequest()` refuses the form (422).
- `ListPage(..., more: true)` (`CountsApproximately`) shows "more than N" for search engines that only estimate.
- `UseCaseMiddleware` wraps every use case (logging, a tenant, a Messenger bus); `UseCaseResolver` decides which use
  case a DTO runs.
- `DtoFactory` creates DTOs. The default passes the `id` to a constructor typed `int`, a backed enum, `Uuid`, or any
  class with `fromString()`; a malformed id in the URL is a 404.
- `DtoRecordProperties` decides what a returned read model is made of (for example the Serializer with groups).

Example: [`src/Crud/Dto`](https://github.com/byfareska/crud-example/tree/main/src/Crud/Dto).

## A driver of your own

Implement `CrudSource` (`driver()`, `supports()`, `searchField()`) and a `CrudDriver` service with the same name
(`listForm`, `list`, `read`, `delete`, `form`, `save`). Autoconfiguration finds it.

`AbstractCrudDriver` is a starting point for an API or an Elasticsearch index:

- `listFormBuilder($crud, sortable: [...], sort: [...], search: 'query')` builds the list form;
- `criteria($form)` returns the filters that have a value;
- operations it doesn't implement are refused.

A read-only driver needs only `name()`, `listForm()`, `list()` and `read()`.

Two drivers with the same name are an error. To change the bundle's driver, decorate it.

Optional interfaces:

- `CopiesRecords` for `DuplicateAction`;
- `ListsIds` for "select all matching" in a single query;
- `PrefillsForm` for `?with[...]` values.
