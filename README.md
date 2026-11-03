# byfareska/crud

[![CI](https://github.com/byfareska/crud/actions/workflows/ci.yml/badge.svg)](https://github.com/byfareska/crud/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/byfareska/crud)](https://packagist.org/packages/byfareska/crud)
[![PHP Version](https://img.shields.io/packagist/dependency-v/byfareska/crud/php)](https://packagist.org/packages/byfareska/crud)
[![License](https://img.shields.io/packagist/l/byfareska/crud)](LICENSE)

A Symfony bundle for admin panels, drawn with Twig and Live Components so every part (a list, a form, a widget, the
palette) reloads on its own:

- **CRUD** pages from one attribute, `#[AsCrud]`, read by a **driver**: `doctrine` (entities) or `dto` (DTOs and use
  cases) - or one of your own;
- a **UI kit** of Twig components (`Ui:*`: page head, modal, drawer, pagination, widget cards, toasts...);
- **panels** (route scopes, e.g. "panel" and "admin"), each with its menu, layout and **dashboard** of widgets the user
  arranges (gridstack);
- a **command palette** whose search algorithm is one service you can replace.

The example application, [byfareska/crud-example](https://github.com/byfareska/crud-example), shows all of it.

## Installation

```sh
composer require byfareska/crud
```

Symfony Flex registers the bundle and adds an admin panel under `/admin` (`config/packages/byfareska_crud.yaml`,
`config/routes/byfareska_crud.yaml`, as below). Without Flex, register it yourself:

```php
// config/bundles.php
Byfareska\Crud\CrudBundle::class => ['all' => true],
```

The bundle needs Twig, Forms, Validator, Translation, Symfony UX Live Component and Chart.js. Doctrine ORM (the
`doctrine` driver, dashboard layouts in a table) and SecurityBundle (roles) are optional. Without SecurityBundle nobody
is signed in and nobody has a role: what declares a `role` is for nobody (see "Panels").

### Routes - one import per panel

```yaml
# config/routes/byfareska_crud.yaml
crud_panel:
    resource: panel # the name of the scope
    type: byfareska_crud

crud_admin:
    resource: admin
    type: byfareska_crud
    prefix: /admin
    trailing_slash_on_root: false
```

Each import adds the dashboard of the panel at its root (`crud_dashboard_<scope>` and its JSON endpoints) and the
pages of the resources of that scope: `crud_<name>_list`, `_new`, `_show`, `_edit` (and those of `#[AsCrudPage]`). The
routes carry the default `_scope`.

Who may see a resource is checked by the bundle on every way to it - its pages, the live list and form, the command
palette: the `role` of the resource **and** the `role` of its panel. `access_control` may guard the addresses as well,
as an extra layer and for the pages of your own, but nothing depends on it.

The live components of a panel's pages are reached under its prefix too - `/admin/_crud/{component}/{action}` (the
route `crud_live_<scope>`, part of the import), not LiveComponent's `/_components/...` - so a panel on a firewall of
its own is whole: an admin panel on `^/admin` with one user class and a partner portal on `^/partner` with another
each re-render their lists and save their forms as the one signed in there. A component on a page of your own (no
panel) keeps LiveComponent's address, and so does one with a route of its own (`#[AsLiveComponent(route: ...)]`).

#### Parameters of the application in the address

A prefix may have parameters - one panel per shop, `prefix: /{shop}/admin` - and so may the path of a resource: the
products of one category, `#[AsCrud(path: '/categories/{category}/products')]`. The bundle hands them on: on a page of
the panel and in the live requests of its components (`/_components/...`, by the address of the page they come from:
the header `X-Live-Url`) they are parameters of the routing context, as Symfony does with `_locale`, so every address
made there has them - links of the rows, the "back" button, the page after a save. The `PageParameters` service tells
them to the code that goes by them: a `DoctrineScope` narrowing the products to the category of the address
([`src/Crud/Doctrine/Maximal/ProductsOfTheCategory.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/ProductsOfTheCategory.php)), a guard, an extension. What they are is the
address's to say: whether the one looking may see that shop, that category, is for the application to check.

What the configuration of a panel cannot know is a `ScopeExtension`'s to say for the request: the name of the shop
of `/{shop}/admin` (`$scope->with(brand: $shop->name)`), the theme of a tenant, a role behind a feature flag - all but
its name and its dashboard, which its routes are made of. The role of a panel is asked about the panel (its `Scope`),
so a voter knows which one; a route import naming a panel neither configured nor named by any resource is refused (a
mistake in its name would be a panel without a role).

A resource whose path has parameters is reached from a page that knows them, not from the menu (no `section`) nor the
palette: `new RouteAction('products', 'crud_category_product_list', 'box-seam', idParameter: 'category')` on the
category leads to its products. The bundle's own names (`id`, `page`, `crud`, `name`, `board`, anything starting
with `_`) are no parameters of the application.

### Configuration

```yaml
# config/packages/byfareska_crud.yaml
byfareska_crud:
    name: Acme # the brand in the menu and the titles
    theme: daisyui # daisyui (the default) | bootstrap5 | tailwind | one of "themes"; '%env(CRUD_THEME)%' will do; a scope may name its own
    # color_themes: [light, dark, cupcake, nord] # the colour themes the picker of "daisyui" offers; default all 35 of daisyUI
    # dark_color_themes: [dark] # which of them are dark (the charts, native controls); default the dark ones of daisyUI
    themes: {} # the application's own themes - see Themes below
    entries: [app] # the Vite entries every page loads; "{theme}" stands for its theme: [panel_{theme}, app]
    layout: '@CrudTheme/panel/layout.html.twig' # what the pages extend
    paths: { new: new, edit: edit, page: page } # the words of the addresses: /products/new, /products/7/edit, /products/page/3
    preview: true # a click on a row opens the preview drawer; AsCrud's preview: decides per resource
    per_page: [15, 30, 50, 100] # the sizes of a page of a list to choose from; AsCrud's perPage: per resource
    default_per_page: ~ # the one shown at first (null = the first); AsCrud's defaultPerPage: per resource
    scopes:
        panel: { icon: house-door }
        admin: { icon: shield-lock, role: ROLE_ADMIN } # label: panel.scope.<name> by default
        # docs: { icon: book, theme: tailwind, entries: [docs] } # a theme of its own, more entries after "entries"
        # sklep: { paths: { new: nowy, edit: edycja, page: strona } } # the words of its addresses, in its language
    command_palette:
        search: Byfareska\Crud\CommandPalette\TextPaletteSearch
        suggested: [] # keys of entries shown before anything is typed
        locales: [] # for byfareska:crud:command-palette:index; empty = framework.enabled_locales
        limit: 14 # items listed at most
        records_per_source: 5 # records a source of records gives at most
    value_themes: [] # templates of blocks of values and layouts for every resource, after its own "theme"
    form_themes: [] # form themes of the forms and filters of the panel, after the theme's own (their blocks win)
    dashboard:
        storage: ~ # doctrine | cache | the id of a DashboardLayouts service
        share_role: ROLE_ADMIN # who may make their layout the default for everybody
        disabled_widgets: [] # keys of widgets left out, e.g. [panel.note] (bundle: panel.clock, panel.note, panel.quick_actions)
        columns: 12 # the columns of the grid (the sizes widgets declare are cells of it)
    chart:
        day_bucket: ~ # the index of a DayBucket service (postgresql | mysql | sqlite) or its id
        stored_timezone: UTC # the zone date columns hold their times in (Doctrine: PHP's); ~ for timestamptz
```

With the `doctrine` storage, add the table: `bin/console doctrine:migrations:diff` (entity
`Byfareska\Crud\Dashboard\Storage\DashboardLayoutRecord`, table `crud_dashboard_layout`).

The bundle depends on no database: what has to be written for one is behind an adapter. `DailySeries::bucket()` (the
SQL of the day of a date column in the zone dates are shown in, for counts by day) comes from a `DayBucket`; by default
the `PlatformDayBucket` that supports the platform of Doctrine's connection (PostgreSQL, MySQL/MariaDB, SQLite), without
Doctrine the one `chart.day_bucket` names. Every `DayBucket` service is tagged `byfareska_crud.day_bucket` and every
`PlatformDayBucket` `byfareska_crud.platform_day_bucket`, so another database is one class of your own implementing
`PlatformDayBucket`; give its tag a higher priority to take the place of a built-in one.

`TimeSeries` counts by an hour, a day, a week (from Monday; `SundayWeek` from Sunday), a month, a quarter or a year
(`Granularity`): `bucket($column, $granularity)` is the SQL of the period (the built-in buckets are `TimeBucket`s too),
`since()`, `periods()`, `fill()` (sums keep their fractions) and `labels()` the rest - `labels($periods, $granularity,
'EEE d')` with an ICU pattern of your own. Its column holds its times in `chart.stored_timezone` - UTC by default, the zone of
PHP when Doctrine wrote them in another (`Europe/Warsaw`), `~` for PostgreSQL `timestamptz`; the periods are in the zone
of the `ChartTimezone` service, the zone of Twig unless you alias it to the one of the user's profile. The dates of the
lists and the details are shown in that zone too (the filter `crud_wall_clock` of the theme), whatever zone the
server formats in.
`ChartFactory::custom($type, $labels, $datasets, $options)` makes a chart of any other type of Chart.js (radar,
scatter, a bar and a line on two axes) in the colours of the page; a font or a colour of a `ChartExtension` stays in
the browser. `$charts->named('sales')->line(...)` names a chart: a `ChartExtension` tells it by `ChartFactory::nameOf()`,
the browser by `data-chart-name` of its canvas. A function the server cannot write in JSON - a tick in "1 234,50 zł" -
is named instead, `'ticks' => ['callback' => 'formatter(currency)']`, and given once in the browser:
`registerChartFormatter('currency', () => (value) => ...)` of `@byfareska/crud`; a listener of `chart-theme:options`
on the canvas changes anything else each time the chart is drawn (another theme, new data), functions included.

### Assets (Vite / Symfony Reprise)

The bundle ships Stimulus controllers and no stylesheet: a theme draws with the classes of its CSS framework, which the
application builds. Icons are Bootstrap Icons in both themes; the dashboard needs gridstack's stylesheet, the date
filters the calendar's (vanilla-calendar-pro) and `@byfareska/crud/styles/calendar.css` after it, in the panel's colours.

The pages load the entries of `byfareska_crud.entries` by the tool of `byfareska_crud.assets`: `reprise` (Vite through
Symfony Reprise, the default and what is shown below), `encore` (Webpack Encore), `importmap` (AssetMapper) or `none`
(a layout of your own loads them). Only that tool's Twig functions are used, so the others need not be installed.

**We recommend Symfony Reprise.** It is what the bundle is developed and tested with: the Tailwind and daisyUI themes
build in the same Vite pipeline as the scripts, and the controllers come from npm with their dependencies. AssetMapper
and Encore work, but the stylesheets and the packages of the panel are then yours to wire up (see below).

```sh
npm install --save-dev file:vendor/byfareska/crud/assets gridstack bootstrap-icons vanilla-calendar-pro
npm install --save-dev tailwindcss @tailwindcss/vite daisyui # the daisyui theme (and tailwindcss() in vite.config.js)
npm install --save-dev bootstrap # the bootstrap5 theme
npm install --save-dev tailwindcss @tailwindcss/vite # the tailwind theme (and tailwindcss() in vite.config.js)
```

```js
// assets/stimulus_bootstrap.js
import { startStimulusApp } from '@symfony/reprise/stimulus';
import { registerControllers } from '@byfareska/crud';

registerControllers(startStimulusApp());
```

**bootstrap5** - the controllers open Bootstrap's modals, drawers and menus through the components the application
hands over (the bundle does not import Bootstrap):

```js
// assets/app.js
import './stimulus_bootstrap.js';
import Dropdown from 'bootstrap/js/dist/dropdown';
import Modal from 'bootstrap/js/dist/modal';
import Offcanvas from 'bootstrap/js/dist/offcanvas';
import { useBootstrap } from '@byfareska/crud';

useBootstrap({ Modal, Offcanvas, Dropdown });

import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import 'gridstack/dist/gridstack.min.css';
import 'vanilla-calendar-pro/styles/core.css';
import 'vanilla-calendar-pro/styles/months.css';
import '@byfareska/crud/styles/calendar.css';
```

**daisyui** (the default) - Tailwind CSS 4 with daisyUI 5; dialogs and drawers are `<dialog class="modal">`, menus
`<details>`, no other JavaScript. The top bar has a picker of daisyUI's colour themes (as on daisyui.com: "system" and
every theme of `byfareska_crud.color_themes`, each in its own colours); the choice is `data-theme` on `<html>`, kept in
the browser's `localStorage` under `panel-theme-daisyui` (the block `theme_storage_key` of `panel/base.html.twig`) and
put back before the first paint. The stylesheet has to build the same themes:

```js
// assets/app.js
import './stimulus_bootstrap.js';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import 'gridstack/dist/gridstack.min.css';
import 'vanilla-calendar-pro/styles/core.css';
import 'vanilla-calendar-pro/styles/months.css';
import '@byfareska/crud/styles/calendar.css';
import './app.css';
```

```css
/* assets/app.css */
@import 'tailwindcss';
@plugin 'daisyui' { themes: all; } /* or the ones of byfareska_crud.color_themes: light --default, dark --prefersdark, nord */
@source '../vendor/byfareska/crud/templates/daisyui'; /* the classes of the theme */
@source '../templates';
```

The forms are drawn with Symfony's `daisyui_5_layout.html.twig`, the fields as wide as their row. The default theme
used to be `bootstrap5`: set `theme: bootstrap5` to keep it.

**tailwind** - Tailwind CSS 4; dialogs and drawers are `<dialog>`, menus `<details>`, no other JavaScript:

```js
// assets/app.js
import './stimulus_bootstrap.js';
import 'bootstrap-icons/font/bootstrap-icons.min.css';
import 'gridstack/dist/gridstack.min.css';
import 'vanilla-calendar-pro/styles/core.css';
import 'vanilla-calendar-pro/styles/months.css';
import '@byfareska/crud/styles/calendar.css';
import './app.css';
```

```css
/* assets/app.css */
@import 'tailwindcss';
@source '../vendor/byfareska/crud/templates/tailwind'; /* the classes of the theme */
@source '../templates';
@custom-variant dark (&:where([data-theme=dark], [data-theme=dark] *)); /* the panel's light / dark switch */
```

Set `resolve.preserveSymlinks: true` in `vite.config.js` when the bundle is linked (composer `path` repository), so its
imports resolve to the application's packages. The pages load the entries of `byfareska_crud.entries` (`[app]`), then
those of their panel (`scopes.<name>.entries`). `{theme}` in a name stands for the theme the page is drawn with: the
example builds one entry per theme and loads the one of the page with `entries: ['panel_{theme}']`. More entries go
next to it - one of the application's own on every page (`[panel_{theme}, app]`), or one of a panel.

### Assets (AssetMapper)

If you already use AssetMapper and do not want Node.js, the panel works with it too - but Symfony Reprise stays the
recommended tool (see above).

With `symfony/asset-mapper` installed the bundle adds its `assets/` to the asset map as `@byfareska/crud`; no npm. Set
`assets: importmap`, and each entry of `entries` is an entrypoint of `importmap.php`:

```php
// importmap.php - next to what the recipes of StimulusBundle and Live Component put there
'app' => ['path' => './assets/app.js', 'entrypoint' => true],
'@byfareska/crud' => ['path' => '@byfareska/crud/index.js'],
'@byfareska/crud/styles/calendar.css' => ['path' => '@byfareska/crud/styles/calendar.css', 'type' => 'css'],
```

```sh
php bin/console importmap:require gridstack gridstack/dist/gridstack.min.css vanilla-calendar-pro \
    vanilla-calendar-pro/styles/core.css vanilla-calendar-pro/styles/months.css bootstrap-icons/font/bootstrap-icons.min.css
```

```js
// assets/app.js
import { startStimulusApp } from '@symfony/stimulus-bundle';
import { registerControllers } from '@byfareska/crud';

registerControllers(startStimulusApp());

import 'bootstrap-icons/font/bootstrap-icons.min.css';
import 'gridstack/dist/gridstack.min.css';
import 'vanilla-calendar-pro/styles/core.css';
import 'vanilla-calendar-pro/styles/months.css';
import '@byfareska/crud/styles/calendar.css';
```

The stylesheet of the theme is the application's as with Vite; the Tailwind themes need it built
(`symfonycasts/tailwind-bundle`), `bootstrap5` takes Bootstrap from `importmap:require bootstrap`.

## Resources

### Doctrine driver

```php
#[ORM\Entity]
#[AsCrud(
    name: 'category', path: '/categories', icon: 'tags',
    source: new DoctrineSource(self::class, search: ['name'], sort: ['name']),
    columns: ['name', 'description'],
    section: 'app.nav.catalog', role: 'ROLE_ADMIN', scope: 'admin',
)]
class Category { /* ... */ }
```

The attribute can be on the entity itself or on a class of its own (`App\Crud\ProductCrud`). The example has the
least a resource needs ([`src/Crud/Doctrine/Minimal`](https://github.com/byfareska/crud-example/tree/main/src/Crud/Doctrine/Minimal)) and one with everything
([`src/Crud/Doctrine/Maximal`](https://github.com/byfareska/crud-example/tree/main/src/Crud/Doctrine/Maximal)). `DoctrineSource`:

| Argument | |
| --- | --- |
| `entity` | the class |
| `form` | a form type, or a `DoctrineForm` (a type with options, or guessed fields); null = the fields guessed from the mapping (relations become choices) |
| `fields` | what the guessed form has; empty = every field and owning relation but the id, the `#[Version]`, columns the database fills (generated, not insertable / updatable) and those whose name looks secret (`passwordHash`, `apiKey`... - see `details:` below); named here, they are in it |
| `createForm`, `updateForm` | the add / the edit form in place of `form` - see below |
| `filters` | fields the list filters by: `'active'` or `'category' => EntityType::class` - each with a small choice of how it compares, see below. A filter of your own: `'priceFrom' => new PriceBound('price')` |
| `search` | fields the typed text is looked for in, also through a relation (`'category.name'`) |
| `searchWith` | how the typed text finds them: a `DoctrineSearch` service of your own (full-text, an exact SKU first); null = every word in one of the fields - see below. Given without `search`, the list has its search field all the same (ElasticSearch needs no fields of the entity) |
| `sortable` | what the column headings sort by: a field, also through a relation, or a `DoctrineSort` of your own: `['name', 'category' => 'category.name', 'productCount' => new ProductCount()]`; empty = every mapped column |
| `sort` | the default order, `['-createdAt']` |
| `operations` | `CrudOperation` cases, e.g. `[CrudOperation::List, CrudOperation::Read]` for a read-only list |
| `countLimit` | the most records the list counts, for a table whose `COUNT` takes long: past it "more than N", paged up to it (filters find the rest), and no "select all matching"; null = all |
| `relations` | the resource whose scopes the entities of a relation are offered in, by the relation (or filter): `['product' => 'archived_products']` - for an entity of several resources; otherwise the first of its entity, of the same panel first |
| `lock` | a record read in a transaction (a delete, a change through `RecordChanges`) is locked for it, `SELECT ... FOR UPDATE`: what its guards go by ("not a paid invoice") cannot change before it is done |
| `countWith` | how many records the list has, in place of the `COUNT` of its query: a `DoctrineCount` service (the estimate of PostgreSQL while nothing filters - `$query->filtered()` - a count kept in a cache); null = `COUNT`, or up to `countLimit` |

The add and the edit form can differ - a password only when a user is added, a SKU not changed once given, other
validation groups: `createForm:` and `updateForm:` take what `form:` takes and replace it for one of them. A
`DoctrineForm` gives a form type options (one of the application's own, or `validation_groups`), or the guessed form
other fields:

```php
new DoctrineSource(
    User::class,
    createForm: new DoctrineForm(fields: ['email', 'plainPassword', 'roles']),
    updateForm: new DoctrineForm(UserType::class, ['validation_groups' => ['Default', 'edit']]),
)
```

The example fixes the SKU of a product in its edit form: `updateForm: new DoctrineForm(ProductType::class,
['sku_fixed' => true])` ([`src/Crud/Doctrine/Maximal/ProductCrud.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/ProductCrud.php)). The "dto" driver has had that from the
start: `create:` and `update:` are DTOs of their own.

A field named in `filters` gets a small choice of how it compares (`Comparison`, the first is what it starts with),
made in the browser: the server is asked only when the filter has a value (the `comparison` controller):

| Field | Compared by |
| --- | --- |
| date, date and time | between two days (from - to, either may be left empty), on, from, until - by the day; by the minute with `DateTimeType` |
| number | =, ≠, >, ≥, <, ≤, between |
| text | contains (any case), does not contain, is, is not, starts with, ends with |
| relation, enum | is, is not (a collection: has, has not); a relation is searched as you type with `symfony/ux-autocomplete`, else a choice of every entity |
| boolean | yes / no, nothing to choose |

The search is in the head of the list, the other filters behind "Filters", each under its label. Those named in
`#[AsCrud(filtersInView: ['category', 'createdAt'])]` are always in view, the rest behind "More filters".

To offer other ways, or to filter by a field of a relation, give the filter yourself:
`'createdAt' => new FieldFilter('createdAt', [Comparison::AtLeast])`,
`'categoryName' => new FieldFilter('category.name', Comparison::TEXT)` - a field of a relation further away
(`'customer.country.name'`) or of an embedded object (`'address.city'`) too; given without a type, the guessed one
keeps the `options:` you give (`query_builder`, `choice_label`). `Comparison::CHOICES` adds "is one of" / "is none of"
(a multiple choice of the same field) and "is empty" / "is not empty" (nothing to type: "orders without an invoice")
to a relation or a choice - `'owner' => new FieldFilter('owner', Comparison::CHOICES)`; `Comparison::PRESENCE` adds the
last two to any other set. What a field becomes is decided by the
`DoctrineFilterGuesser` service (`MappingFilterGuesser`); alias it to your own to change that for every resource, and
`RelationFilterField` to choose relations another way. The field is a `ComparisonType` (its data a `Compared`), which
a list DTO can use as well.

A way of your own to compare - "in the last N days", a LIKE pattern, "near" - is a `DoctrineComparison` (a
`ComparisonKind`: its `key()` in the address, its `label()`, whether it `needsValue()` or `isMany()`) offered next to
the bundle's: `new FieldFilter('updatedAt', [...Comparison::DATE, new LastDays()], IntegerType::class)`; its `apply()`
adds to the query of the list what it means (see its docblock). How a text is compared ("contains", "starts", =) is
the `DoctrineTextMatch` service: `LOWER()` of both sides by default; alias it for a column of a case-insensitive
collation as it is (its index is used) or for `unaccent()` of PostgreSQL.

With `symfony/ux-autocomplete` (its routes imported, as its recipe does) a relation filter asks
`RelationAutocompleter` (alias `byfareska_crud_relation`) for the entities: every typed word is looked for in the
fields the list of the related entity searches, else in its texts; only somebody who may see the list gets them.
Ten of them a page, or `'options' => ['extra_options' => ['per_page' => 25]]` of the filter; the text of an entity is
its `__toString()`, or what a `RelationChoiceLabel` service says of it ("Kowalska, Anna (PL123)").

Its fields need the CSS of Tom Select in the look of the theme (turn off the recipe's `autoimport` of
`tom-select.default.css` in `assets/controllers.json`): `tom-select.default.css` and then
`@byfareska/crud/styles/tom-select.daisyui.css` for "daisyui"; `tom-select/dist/css/tom-select.bootstrap5.css` for
"bootstrap5", dark mode included; `tom-select.default.css` and then `@byfareska/crud/styles/tom-select.tailwind.css`
for "tailwind".

A date filter is picked in a calendar (the `date-range` controller): for "between" two months side by side, the
days of the range highlighted, the field showing them in words ("Wed, Nov 11 — Sun, Nov 15"); for the other
comparisons one day. A date and time column is filtered by the day; given `DateTimeType` it is by the minute and the
calendar has the time below it (from and to for "between"):

```php
filters: ['createdAt' => DateTimeType::class],
// or: 'createdAt' => new FieldFilter('createdAt', [Comparison::Between], DateTimeType::class)
```

The controller works on any page - see its docblock.

What the database refuses becomes an error of the form or of the delete, not a 500: a unique key
(`crud.error.taken`), a foreign key (`crud.error.in_use`), a deadlock or a lock that waited too long (`crud.error.retry`,
503), another constraint (`crud.error.refused`). That is the `DoctrineExceptionMapper` service: decorate it to say what a
constraint of your own means, at its field (`new CrudFailure(409, ..., 'sku', translationKey: 'app.sku_taken')`). An
entity manager a failed flush closed is made anew.

The id of an entity in an address is the `DoctrineIdCodec`'s: an id of one field as it is (`7`, a uuid), one of
several fields - an order line by its order and number - as its parts in the order of the mapping, each encoded and
joined by `,` (`12,3`). Such an entity is listed, shown, edited and deleted like any other. Alias the codec for ids of
your own kind (a hash of the id, a slug) everywhere at once.

A source naming an entity manager (`DoctrineSource(manager: 'tenant_a')`, or given one by a `CrudDefinitionExtension`)
reads its related entities from it too - the choices of its relation filters, the entity of `?with[category]=5` -
when that manager maps them (`DoctrineManagers`), not from the first manager that does.

An entity with `#[ORM\Version]` is locked optimistically: its edit form remembers the version it was opened with
(the hidden field `_version`), and a save after somebody else's is refused (`crud.error.changed`) instead of writing
over it.

How the driver makes and writes entities is two services, aliased (or decorated) for every resource - they get the
`AsCrud` and decide by it:

| Interface | The bundle's | |
| --- | --- | --- |
| `DoctrineEntityFactory` | `ConstructorEntityFactory` | the new entity of the add form: a named constructor, a factory of the domain; null leaves it to the form type's `empty_data` |
| `DoctrinePersister` | `FlushingPersister` | the save (`persist` + `flush`) and the delete (`remove` + `flush`): a method of the domain in place of removing (`$order->cancel()`), a repository or a service |

A guessed form of an entity whose constructor needs arguments is refused with what to do: a form type, or
`new DoctrineForm(fields: [...], options: ['empty_data' => fn (FormInterface $form) => new Order(...)])`.

The query is open to the application, through `DoctrineQuery` (the `QueryBuilder` with the entity as `e`,
`path('category.name')` joining a relation once, `parameter()` for a fresh name) - for the list a `DoctrineListQuery`,
which has the submitted list form too:

- A `DoctrineScope` service says which entities of a resource (or of every one) there are for the panel at all: "only
  my records", "those of the last week", by a join of another entity too. It narrows the list (so the palette and the
  bulk actions), every record found by its id - its details, preview, edit form and deleting; another one is not
  found - and the entities a relation filter offers as one types
  ([`src/Crud/Doctrine/Maximal/ProductsAddedSoFar.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/ProductsAddedSoFar.php)). A choice of every related entity and the relation
  fields of a form are up to their form type (`query_builder`).
- A filter of your own is a `DoctrineFilter` (what it is, e.g. `new PriceBound('price', upper: true)`) and the
  `DoctrineFilterHandler` service it names: `field()` makes its form field, `apply()` narrows the query by its value.
- An order of your own is a `DoctrineSort` given in `sortable` under the name of its column (and usable in `sort`),
  and the `DoctrineSortHandler` service it names: `apply()` adds it, an expression selected as `HIDDEN` first
  ([`src/Crud/Doctrine/Maximal/ProductCountHandler.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/ProductCountHandler.php): the categories by how many products they have).
- A `DoctrineListExtension` service changes the query of the list alone, after the scopes, the search, the filters
  and the order: a join that reads a relation with the records
  ([`src/Crud/Doctrine/Maximal/ProductCategoryJoin.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/ProductCategoryJoin.php)), records only the list leaves out. An order it adds
  comes after that of the headings; the id comes last, so a page keeps its order. What it selects besides the
  entity is in its item under its alias - a column the database computes:
  `$query->query->addSelect('(SELECT COUNT(o.id) FROM App\Entity\Order o WHERE o.customer = e) AS orders')`.
- A `DoctrineItems` service adds to the items what a service of yours knows (the stock of another system, the orders
  of the customers counted by one query): `extend($crud, $entities, $items)` gets a whole page at once - and a record
  read by its id as a page of one.

How the typed text finds the entities is a `DoctrineSearch` service: the bundle's `WordsSearch` wants every word in
one of the `search` fields, in part and any case (`LOWER(...) LIKE`), which works on every database and uses no index.
A search of your own - full-text of the database, a search engine giving ids, a whole SKU being that product alone - is
a class implementing `DoctrineSearch` (`apply(DoctrineQuery $query, array $fields, string $text)`): alias the interface
to it for every resource, or name it in one source, `searchWith: SkuSearch::class`
([`src/Crud/Doctrine/Maximal/SkuSearch.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/SkuSearch.php), which hands anything else to `WordsSearch`). The relation filters
that offer the entities as one types find them the same way.

A page of the list has at most as many records as the largest size the resource offers (`perPage`, else
`byfareska_crud.per_page`) - an address cannot ask for more.

A query joining what can give an entity more than one row - a collection (`search: ['products.name']`), an entity by
a condition - is paged by distinct entities, so a page still has as many records as it should. To narrow by a
collection without that, a condition of a subquery does it: `EXISTS (SELECT ...)`, `MEMBER OF`.

### DTO driver

Every operation is a DTO naming its use case. The panel only runs them, so the same DTOs can serve an API or a console
command, and the records can live anywhere. The example has the least a resource needs, a list alone
([`src/Crud/Dto/Minimal`](https://github.com/byfareska/crud-example/tree/main/src/Crud/Dto/Minimal)), and one with everything ([`src/Crud/Dto/Maximal`](https://github.com/byfareska/crud-example/tree/main/src/Crud/Dto/Maximal)).

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

final readonly class ListTasks implements Query // or Command; both are invokable services
{
    public function __invoke(ListTasksRequest $request): ListPage { /* ... */ }
}
```

- **list**: a form type DTO; `page`, `perPage` (one of `AsCrud::$perPage`), `sort` (and `fields`) are driven by the
  list, every other field is a filter. Its use case returns a `Page` (`ListPage`), items as arrays with an `id`. A
  total counted only so far (a search engine's "10 000+") is a page implementing `CountsApproximately` -
  `new ListPage(..., more: true)`: the list says "more than N" and offers no "select all N".
- **read**, **delete**: a public `id`. `read` returns an object whose public properties are the record.
- **create**, **update**: form type DTOs; `update` has a public `id`, filled from the address, and starts with the
  values `read` returns, property by property of the same name: an object goes as it is when the property is typed
  for it (an enum, a date, a value object), else as text (an enum to a `string` property: its value); anything else
  structured is the DTO's to take - `FillsFromRecord::fillFrom($record)` is called last with what `read` returned.
  A use case that returns an object with an `id` sends the user to the new record.
- `#[UseCase(errors: [TaskNotFound::class => new Failure(404, message: 'crud.error.not_found')])]` says what the
  exceptions of a use case mean; a `UseCaseErrorMapper` service says it for every use case at once. A
  `ValidationFailedException` (Symfony Validator) of the domain shows each message at its field of the form; a
  `ValueError` of `toRequest()` (`Enum::from()` of no case) refuses the form (422), no 500.
- A `UseCaseMiddleware` service wraps every use case the panel runs (`handle($dto, $request, $next)`): a log, the
  tenant set for the domain, a command sent to a Messenger bus in place of running it. Which use case a DTO runs is the `UseCaseResolver`'s
  (by default its `#[UseCase]`, read once a class): alias it for a DTO of a library with no attribute, or another use
  case for a tenant; a middleware that goes by the use case (a transaction for a `Command`) asks it too.
- A record that a use case returns may be a read model: its public properties and what its getters say (`getName()`,
  `isActive()`). A list use case may return a list of them (no `Page`): the list pages it itself (a list DTO without
  "page"). The DTOs are made by the `DtoFactory` service (the bundle's: the constructor, given the `id` when it takes
  one - a readonly id - of its type: `int $id`, a backed enum, `Uuid $id` and any class with `fromString()`; an
  address that is no id of that type is a 404); a use case adding a record may return its id itself (a number, a
  Uuid) or something with an `id` (a property, `getId()`). What a record is by property is the
  `DtoRecordProperties` service when you alias one - the Serializer with groups, for a read model whose getters
  must not all be called (lazy collections of an entity, a getter with a side effect).

### Your own driver

Implement `CrudSource` (`driver()`, `supports()`, `searchField()`) and a `CrudDriver` service with the same name
(`listForm`, `list`, `read`, `delete`, `form`, `save`); it is found by autoconfiguration.

`AbstractCrudDriver` is a start of one - a list of an API, an index of ElasticSearch: `listFormBuilder($crud,
sortable: [...], sort: [...], search: 'query')` makes the list form with what the list drives (the page, its size,
the fields, the order), `criteria($form)` gives the filters with a value, and what it does not do (delete, the forms,
save) is refused. A read-only driver is `name()`, `listForm()`, `list()` and `read()`. Two drivers of one name are a
mistake: to change the bundle's, decorate it.

### More about a resource

- `columns: ['name', 'price' => 'money']` - a type picks the Twig block a value is printed with: `type_money`. Blocks
  are looked for from the most specific (`crud_product_price_list`, `field_price`, `type_money`, `value`) in the
  resource's `theme:` template, then `@CrudTheme/crud/values.html.twig` of the panel's theme. A format in place of the
  type prints it instead: `'stock' => new ComponentFormat('App:StockLevel')` - see below.
- `layout: [new Fieldset('basics', ['name', new Row(['sku', 'category'])]), new Tab(...)]` - the form and the details
  in tabs, columns, fieldsets and rows. Widths are 12ths (`new Column(8, [...])`, `new Field('sku', cols: 4)`), or a
  `Span` for other breakpoints (`new Column(new Span(lg: 8, xl: 9), [...])`), which every theme draws with its own
  grid; `class:` adds classes of its own.
  Parts of the layout:
  - `new When(PaidOrder::class, [new Tab('refund', ...)])` - on a page only when a `LayoutCondition` service says so,
    asked with the record (null on the add form) and whoever is looking: `shows(AsCrud $crud, ?RecordView $record)`.
    It stands for its children, checked where it stands. In the form what it keeps off the page is not in the form at
    all (fields and sections): nothing sent for it is taken. A `PageLayoutCondition` is also told the page (the forms
    or the details) and the `options:` of its When - one class for every status:
    `new When(StatusIs::class, [...], options: ['status' => 'paid'])`.
  - `new Fieldset('schedule', [...], showIf: 'trigger=schedule')`, `new Tab('company', showIf: 'kind=company')` - in
    the form, shown only while the fields say so (the `show-if` controller; a hidden tab takes its button with it).
  - A part of your own - a notice, a separator, a card - implements `PlacedLayoutNode` (`placedIn()`: "top" or the
    groups it may stand in; `holds()`, for a group) and is drawn by the block `layout_<short class name>` of the
    resource's theme or of `@CrudTheme/crud/layout.html.twig` - or `layout_<blockName()>` of a `NamedLayoutNode`,
    when two modules have a class of one short name.
- `actions: [new RouteAction(...), new ComponentAction(...)]` - what else can be done with a record, see below.
- `bulkActions: [new BulkDeleteAction()]` - what can be done with the records chosen in the list, see below.
- `details:` - what the details page and the preview drawer show; without it every property of the record but those
  whose name looks secret (`passwordHash`, `resetToken`, `apiKey`... - words of `AsCrud::SECRET`; named in `details:`
  they are shown). `hidden: ['internalNote']` - properties never shown in the details, whatever they are called. The
  record a guard, an action or an event gets still has them all. With the doctrine driver a record is the fields and
  relations of its entity's own - not the inverse side of a collection (the products of a category), which would read
  every one of them for each guard and delete; named in `details:` it is read.
- `preview: false` - a click on a row does not open the preview drawer; the first column leads to the details page.
  Without it, `byfareska_crud.preview` decides (default true).
- `afterSave: [AfterSave::New, AfterSave::Edit]` - buttons of the form besides "save": "save and add another" (the add
  form again; only when adding), "save and keep editing". Where they lead is the `CrudReturn`'s, when it is a
  `CrudSaveReturn` (`afterSaveTo()`; the bundle's is). A button of your own - "save and go to the lines", "save and
  send" - is a `CrudSaveButton` service (`name()`, `label()`, `isFor($crud, $id)`, `afterSave()` giving where it leads;
  [`src/Crud/Doctrine/Maximal/SaveAndSeeProducts.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/SaveAndSeeProducts.php)). `RecordSaving::$then` and `RecordSaved::$then` say which
  button saved, so a listener does what it promises in the transaction of the save. Validation groups are the form's:
  a "save as draft" checking less is a form type whose `validation_groups` closure looks at the data.
- `requirements: ['category' => '\d+']` - what the parameters of your application in its path
  (`/categories/{category}/products`) are made of.
- `idPattern: '\d+'` - what the id in the address is made of: `/products/export`, a route of your own, is then not
  taken for a record.
- `pageTemplates: ['list' => 'crud/category_list.html.twig']` - a page of the resource (`list`, `show`, `form`, `page`)
  drawn by a template of yours, which extends the theme's (`@CrudTheme/crud/list.html.twig`) and changes a block
  (`content` with `parent()`) - [`templates/crud/category_list.html.twig`](https://github.com/byfareska/crud-example/blob/main/templates/crud/category_list.html.twig).
- `options: ['export' => true, 'audit' => 'full']` - what parts of your application know the resource by; the bundle
  does not read them: an extension, a listener or a page of yours does (`$crud->option('export', false)`), and a
  `CrudDefinitionExtension` may change them.
- `perPage: [5, 10, 25]`, `defaultPerPage: 10` - the sizes of a page of the list to choose from below it (one alone
  means no choice) and the one shown at first; without them `byfareska_crud.per_page` and `default_per_page` decide.
  A list DTO without a "page" field returns every record and the list pages them itself. The size chosen goes to the
  address as `?size=30` (left out for the one shown at first).
- `listHead`, `listActions`, `listedIn`, `own: true` (the record of the one signed in) - see the docblock of `AsCrud`.
  The record of an own resource is the id of the user, unless an `OwnRecordResolver` of the resource says another
  ("my company": `ownId($crud)` returns the id of the user's company).
- The id of the one signed in (`Actor::id()`, by `UserIdMapper`) is `getId()` of the user, without its class: two
  user classes in one panel (employees and customers by a chain provider) with the same ids would share their
  dashboards and palette history - give them an `UserIdMapper` of your own (`'customer:' . $user->getId()`).
- `#[AsCrudFormSection]` / `#[AsCrudDetailsSection]` - another part of the application adds a section to the form or the
  details of a resource. A form section is a DTO of its own (`update:`, with `read:` for what it starts with) saved by
  its use case right after the record, in the same transaction; with `create:` (a DTO, which may be that of `update:`)
  it is in the add form too, given the id of the new record once it is saved - a note the use case refuses leaves the
  new record unsaved ([`src/Crud/Doctrine/Maximal/ProductNoteSection.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/ProductNoteSection.php)). `role:` gives a section only to
  somebody who has it (asked with the resource as the subject): anybody else's form has no such section, nor saves it.
- `CrudFormExtension` - a service changing the add and edit forms for the request, whatever the driver: a field only a
  role may change (`$builder->get('creditLimit')->setDisabled(true)` - shown, what is sent ignored) or none at all
  (`$builder->remove(...)`); it gets the record being edited ([`src/Crud/Dto/Maximal/PriorityForAdmins.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Dto/Maximal/PriorityForAdmins.php)).
  What the address gives a new record (`?with[creditLimit]=...`) is set after the extensions and the `When`s of the
  layout, only for fields still in the form, enabled and mapped - a driver of your own does that by implementing
  `PrefillsForm` (one without it gets `$with` in `form()`, before the extensions, and has to leave such fields out).
- `#[AsCrudPage]` / `#[MapCrud]` - a page of its own about a record, see below.
- Events: `RecordSaving` and `RecordDeleting` before (a listener refuses by throwing a `CrudFailure`, e.g.
  `new CrudFailure(409, translationKey: 'app.invoice.paid')`: nothing is saved or deleted and its text is shown), then
  `RecordSaved` and `RecordDeleted` - see "Events and transactions".

### Resources declared by code, and changed for a request

The arguments of an attribute are constants. What depends on more than that goes through two services, both
autoconfigured:

- A `CrudResourceProvider` declares resources by code, next to those of `#[AsCrud]`: `resources()` yields `AsCrud`
  objects - one per report of the configuration, those of a module that is switched on. The routes are made of them
  and kept in the cache, so what it yields is the same in every request; a change of its class rebuilds them in debug,
  a change of what it reads needs `cache:clear`.
- A `CrudAdditionsProvider` does the same for pages and sections: `pages()`, `formSections()` and
  `detailsSections()` yield `AsCrudPage`, `AsCrudFormSection` and `AsCrudDetailsSection` objects - "ERP integration"
  under the details of every resource a YAML file names.
- A `CrudDefinitionExtension` changes a resource for the request being answered: `resource()` names it
  (`CrudDefinitionExtension::EVERY` for all), `extend(AsCrud $crud)` returns it changed with `$crud->with(...)` - a
  column only some role sees, the columns of a tenant, an action behind a feature flag, other page sizes. Every way to
  the resource sees what it returns (the list, the details, the forms, the menu, the palette); it is asked once a
  request per resource. `with()` takes the arguments of the attribute but those the routes are made of (`name`,
  `path`, `scope`, `own`, `listedIn`) and `role` (`AsCrud::CHANGEABLE`), and checks them like a declaration. The
  source changes only for one of the same class, entity, driver and operations: a filter and its sorting for the
  column of a role, the entity manager of a tenant, another count limit -
  `$crud->with(source: $crud->source->with(filters: [...$crud->source->filters, 'margin']))` (`DoctrineSource::with()`).

```php
final readonly class TaskNumberForAdmins implements CrudDefinitionExtension
{
    public function __construct(private Actor $actor) {}

    public function resource(): string { return 'task'; }

    public function extend(AsCrud $crud): AsCrud
    {
        return $this->actor->isGranted('ROLE_ADMIN')
            ? $crud->with(columns: [$crud->column('id'), ...$crud->columns])
            : $crud;
    }
}
```

An extension takes operations away for the request with `$crud->refusing(CrudOperation::Delete)` ("this plan does
not delete"): see below. What one extension refused, the next cannot give back.

`CrudRegistry::get()` and `all()` give a resource as it is in the request, `declared()` as declared
([`src/Crud/Dto/Maximal/TaskNumberForAdmins.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Dto/Maximal/TaskNumberForAdmins.php)). The entries of the palette are those of the declarations
(an index is built of them), offered as the resource is in the request.

### The list and the form of a resource's own

`#[AsCrud(listComponent: 'App:ProductList')]` draws the list of a resource with a live component of the application
extending `CrudList`, `formComponent:` its forms with one extending `CrudForm` - for live actions the bundle's have not
(a toggle on a row, an order dragged, an export of what is filtered, a value looked up as one types). It is drawn by the
bundle's template in the theme in use; services are arguments of its actions, which LiveComponent autowires:

```php
#[AsLiveComponent('App:ProductList', template: CrudList::TEMPLATE)]
final class ProductList extends CrudList
{
    #[LiveAction]
    public function toggleActive(#[LiveArg] string $id, EntityManagerInterface $entities): void
    {
        $crud = $this->getDefinition(); // who may see the list
        // ...the guards ($this->guards), the record through the driver ($this->records), the change, a toast
    }
}
```

`getDefinition()` checks who may see the list (or the form), `form()` is the submitted list form, `refuse()` shows a
failure at a field of the form; the services of the constructor are protected. Do not override the constructor - the
bundle adds what it needs to it: a service of your own is an argument of a live action, or, for what the template
reads, set once by a method marked `#[Required]` (`public function setStats(ProductStats $stats): void`). A `LiveListAction` puts the button on
the rows ([`src/Crud/Doctrine/Maximal/ProductList.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/ProductList.php)).

A list can be drawn on any page - the lines under the details of an order (an `AsCrudDetailsSection`), the invoices
and the orders of a customer side by side:

```twig
{{ component('Crud:List', {
    resource: 'order_line',
    fixedFilters: {order: {comparison: 'eq', value: id}},
    urlPrefix: 'lines',
}) }}
```

What a list starts with when its address says nothing is a `ListDefaults` service's to say (`filters($crud)`,
`sort($crud)`; the size is `PageSizes`'): the view the one looking chose last, "my open orders". `ListStateChanged` is
dispatched once a request when they change the filters, the order or the size - what a listener keeps for it.

`fixedFilters` are always applied and never shown: not in the toolbar, not taken out by "clear filters" nor by what the
browser sends, and a bulk action gets only records they match. The state of a list is in the address of its page
(`?filters[...]`, `/page/2`, `?size=`, `?sort=`, `?preview=`); `urlPrefix: 'lines'` names it `?lines_filters[...]`,
`?lines_page=2`... so two lists of a page keep theirs apart, and `inUrl: false` keeps it out of the address.

### Services to replace or decorate

What the pages do around the drivers goes through interfaces, each aliased to the bundle's `Default*` service - alias
the interface to your own, or decorate it with `#[AsDecorator(CrudReturn::class)]` to change only a part:

| Interface | What it says |
| --- | --- |
| `CrudReturn` | where the pages of a record lead: `url()` (their "back"), `afterSave()` (e.g. the edit form again, the details of a new record), `afterDelete()` |
| `CrudAccess` | whether a resource, an operation of it and a page of a record are for the one asking - one more rule (a tenant, a licence) for every way at once |
| `RecordDeletion` | how a record is deleted from the list, its pages and the bulk delete: around it a transaction or an archive, or another way (marked deleted) |
| `FailureMessage` | the text of a refusal (a form, a toast, an error page) |
| `PageSizes` | the sizes of a page of a list and the one shown at first - e.g. what the user chose last |
| `RecordTransaction` | what a save and a delete run in, all or nothing - see "Events and transactions" |
| `CrudRecords` | how a record is read for the pages, the preview, the guards - e.g. a value computed for every resource added to what it shows |
| `CrudPages` | what happens when any page of a resource opens (`#[MapCrud]` too) - e.g. remember the records seen last |

### Events and transactions

| Event | What it has |
| --- | --- |
| `RecordSaving` | `crud`, `id` (null: added), `data` - the entity or the DTO the form made, to change (its author) - and `before`: the record as it was, every property (a `RecordView`; null when added) |
| `RecordSaved` | `crud`, `id`, `created`, `data` (what was saved) and `before` - what an audit log needs for a diff |
| `RecordDeleting` | `record`: every property |
| `RecordDeleted` | `crud`, `id`, `record`: what it was |
| `RecordChanging` | `record` (as it is before), `action` (its name), `input` - a change otherwise than by the form: a `RunAction`, a change of your own through `RecordChanges` |
| `RecordChanged` | `crud`, `id`, `action`, `before`, `input` |
| `BulkActionRunning` | `action`, `selection` (the records chosen, the `input` of the dialog) |
| `BulkActionDone` | `action`, `selection`, `result` (what its handler told) |

A save is all or nothing: `RecordSaving`, the driver, the sections of the form (`#[AsCrudFormSection]`) and
`RecordSaved` run in one transaction, and so do the reading of the record, its guards, `RecordDeleting`, the driver and
`RecordDeleted` - a guard goes by the record the delete finds. A listener of `RecordSaving`, `RecordDeleting`,
`RecordChanging` or `BulkActionRunning` refuses by throwing a `CrudFailure`: nothing is done, its message is shown.

Every change of a record otherwise than by its form goes through the `RecordChanges` service, so an audit log
listening to `RecordSaved`, `RecordDeleted` and `RecordChanged` misses nothing: `change($crud, $id, 'archive',
fn (RecordView $before) => ...)` reads the record in its scopes, asks its guards (of `Update`, and of the action -
`RecordActionGuard` by its name), dispatches `RecordChanging`, does the work and dispatches `RecordChanged`, all in the
transaction of the resource. A `RunAction` goes through it; a bulk action of your own, a message handler or a
controller does well to call it for each record it changes. A section that
refuses, or a listener of `RecordSaved` that throws, undoes the record too; what a listener writes (an audit log) is kept
with it. With Doctrine ORM the transaction is one of the connection of the resource's entity manager - of the default
one for a resource of another driver (`DoctrineRecordTransaction`); without it there is none. The `RecordTransaction`
service says it: alias it for another store. A resource says it of itself with `options: ['transaction' => false]`
(none: a use case writing through a message bus) or the name of an entity manager (a resource of DTOs writing to
another database). What must happen only once the record is kept
(an e-mail, a message to another system) is not done in `RecordSaved` itself: dispatch a message through a transport
written in the same transaction (Messenger's doctrine transport), or do it on `kernel.terminate`.

### Who may do what with a resource

`AsCrud::$role` is who the resource is for at all; `roles` narrows an operation further, by the value of its
`CrudOperation`:

```php
#[AsCrud(name: 'task', /* ... */, roles: ['delete' => 'ROLE_ADMIN'])] // everybody lists, reads, adds and edits
```

Both are asked with the resource (its `AsCrud`) as the subject, so the role may be an attribute a voter of the
application decides: `roles: ['create' => 'TASK_CREATE']`. An operation the one asking may not do is refused for the
request (`AsCrud::refusing()`, which an extension can do as well, for any reason): `can()` says no wherever it is asked -
its buttons, the "new" button, the bulk delete, the menu and the palette go away - and its pages and live actions answer
403. The routes stay: they are the same for everybody. The example lets only an administrator delete a task
([`src/Crud/Dto/Maximal/TaskCrud.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Dto/Maximal/TaskCrud.php)). The roles of actions, bulk actions and `AsCrudPage` are asked about the
resource too.

### Who may do what with a record

`AsCrud::$role`, `roles` and the operations of the source say what can be done with a resource. A `RecordGuard` service says it
of one record: `allows(CrudOperation $operation, RecordView $record)` for its `resource()` (or `RecordGuard::EVERY`),
`fields()` naming what it needs of an item of the list. What it refuses is not offered (the buttons go away, the
record is left out of the palette) and its page answers 403. The example keeps a done task from being edited
([`src/Crud/Dto/Maximal/DoneTaskGuard.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Dto/Maximal/DoneTaskGuard.php)). Which entities somebody sees at all - listed or opened by their
address alike - is a `DoctrineScope` (see the Doctrine driver).

A guard (or a `RecordActionGuard`) that also implements `ExplainsRefusal` says why it refused:
`refusal($what, $record)` returns a `CrudFailure` with a translation key ("A paid invoice is not deleted."). The
button is then shown disabled with that as its hint instead of going away, and a page or a live action refused
answers with it instead of "Forbidden".

Voters of your application about an entity (an `OrderVoter` of `EDIT` and `REFUND`) are asked by extending
`VoterRecordGuard` (Read, Update, Delete as `VIEW`, `EDIT`, `DELETE`; `attributeOf()` to say otherwise) and
`VoterActionGuard` (an action by its name: `refund` is `REFUND`) with just `resource()`: the entity of the record is
read by its id once a request.

### Rows of the list

A `RecordRow` service adds attributes to the row of a record - a class marking what is overdue, a data attribute for
a Stimulus controller of yours: `attributes(RecordView $record)` for its `resource()`, `fields()` naming what it needs.
The classes of all of them are put together. The example dims a product that is not for sale
([`src/Crud/Doctrine/Maximal/InactiveProductRow.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/InactiveProductRow.php)).

### Actions of a record

Every record has the built-in actions for what its resource can do: details, edit and delete. Declared in `actions:`
by their class, they take the place of the built-in ones there: `new EditAction(inline: true)`,
`new DeleteAction(message: 'app.invoice.delete')`; `new NoAction('details')` takes one away (the resource still reads
the record). `priority:` (of `RouteAction`, `ComponentAction`, `LiveListAction`, or a `PrioritizedAction` of your own)
places an action: lower first, the others 0 in the order details, edit, `actions:`, pages, delete.

`actions:` adds what else can be done with every record. It is offered wherever the record has its actions: a button on
its row of the list (`inline: true`) or an item of the row menu, the preview drawer, the details page and the edit form.
Its text is `crud.<resource>.action.<name>`; `role:` narrows who is offered it.

- `new DuplicateAction()` - the add form filled in as a copy of the record (`/products/new?from=12`): what the record
  has for the fields of the add form the one looking may fill in (the doctrine driver: fields and owned relations,
  a relation of many as the same entities; the dto driver: what `read` says, by property). The record is read in its
  scopes and only when its guards allow it. What else a copy takes - the lines of an order, "(copy)" in the name - is
  a listener of `RecordCopying` (`$event->data` is the new entity or DTO). A driver of your own implements
  `CopiesRecords`.
- `new RouteAction('invoices', 'app_customer_invoices', 'receipt', fieldParameters: ['customer' => 'customerId'])` -
  a parameter of the route taken from a field of the record (asked of the items of the list too).
- `new RouteAction('login-as', 'app_login_as', 'key')` - a link to a route with the parameter `id`; more of them
  `parameters: ['status' => 'open']`, a text of its own `label:`, a new tab `target: '_blank'`, a visit by Turbo
  `turbo: true` (by default a full page load).
- `new ComponentAction('restock', 'App:Restock', 'box', fields: ['stock'], props: [...])` - a Twig component draws it
  itself. It gets `record` (a `RecordView`: the resource, the id, the values of the record and, in the list, the page
  of the list with the other records, `list`), `place` (an `ActionPlace`:
  `Row`, `Menu` or `Bar`, the buttons of a page or the drawer), `button` (its text and icon) and the props; it declares
  them (public properties, or `{% props %}`). `fields:` are what it needs of an item of the list besides the columns.
- `new LiveListAction('toggle-active', 'toggleActive', 'toggle-on')` - a button that runs a live action of the list
  with the id of the record: one of a list of the resource's own (see "The list and the form of a resource's own").
  Only where the list is - its rows, the row menu, the preview drawer.
- `new RunAction('approve', 'check2', ApproveInvoice::class, message: 'app.invoice.approve_question')` - something
  done to the record on the server: "approve", "archive", "send again" - the counterpart of a bulk action for one
  record. Its `RecordActionRunner` service (`run(RunAction $action, RecordView $record, ?string $input)`, returning a
  `BulkResult` to show or to redirect, or null for "Done") is called through `RecordChanges` - in a transaction, the
  guards of `Update` (`update: false`: only those of the action) and of the action asked, `RecordChanging` and
  `RecordChanged` dispatched. In the list it is the live action `run` of the list; on the details page and the edit
  form the `Crud:Run` component, which opens the page again. `message:`, `hint:` and `input:` (a `ConfirmInput`,
  checked on the server) ask first; `danger: true` draws it red. Never a link: nothing is changed by a GET a browser
  could prefetch. The example puts a product on sale and takes it off this way
  ([`src/Crud/Doctrine/Maximal/ToggleProductActive.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/ToggleProductActive.php)). A handler of your own whose action is done on the
  server implements `RunsRecordAction` too; `RecordActions::run()` is the way to it.
- A kind of your own is two classes: a `RecordAction` (what it is: `name`, `role`, `inline`, `fields()`, `handler()`)
  and the `RecordActionHandler` service it names, which makes its `ActionButton` for a record - a link or a button with
  attributes of your choice (a Stimulus controller of yours), or `null` when the record has nothing to offer. The theme
  draws it in each place (`@CrudTheme/crud/actions.html.twig`, `crud_action(button, place)`) with the first block
  there is of `crud_<resource>_action_<name>_<place>`, `action_<name>_<place>` and `action_<place>`, in the `theme:`
  of the resource first: one action of one resource drawn otherwise without copying them all. The example copies the
  SKU of a product this way ([`src/Crud/Doctrine/Maximal/CopyAction.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/CopyAction.php), with its own Stimulus controller
  [`assets/controllers/clipboard_controller.js`](https://github.com/byfareska/crud-example/blob/main/assets/controllers/clipboard_controller.js)).
  A handler that also implements `MakesButtons` gives one action several buttons, by the record: the transitions of
  a Symfony Workflow it may take now (`buttons($action, $record)` yields an `ActionButton` for each).
- The colour of a button says what it does (`ActionTone`, Bootstrap's names, drawn alike by both themes): adding
  (`DuplicateAction`, "new") is `Success` - green, editing `Warning` - yellow, deleting `Danger` - red, the details
  `Info` - blue. Any other is `Secondary` - grey - (`Danger` with `danger: true`) unless it says otherwise:
  `tone: ActionTone::Info` on `RouteAction`, `RunAction`, `LiveListAction`, `ComponentAction` and on an
  `ActionButton` of a handler of your own. The main button where it is (`primary`) is filled, the others outlined.

### Bulk actions

`bulkActions:` lets the rows of the list be chosen (a checkbox on each, one for the page in the heading) and shows what
can be done with the records chosen in a bar over the list. `new BulkDeleteAction()` deletes them, one after another the
way a record deleted on its own goes (guards, events); what is refused stays and is counted in the toast.

When the whole page is chosen and the filters match more records, the bar offers to choose every one of them, on
every page ("select all N matching"): the list keeps that as a flag, not as thousands of ids, and forgets it when the
filters change. The `BulkSelection` an action gets then has `allMatching`, `count` (the number, for a button) and
`ids`, read from the list when asked for first - page by page, with its filters and scopes, before anything is done,
only those the guards of the resource let be read (the records the list shows, by the items it read).
An action written for the ids chosen works for every match as it is; one that will not do that many offers no button
when `$selection->allMatching`. A driver implementing `ListsIds` gives those ids by one query (the doctrine driver: the
ids alone, in the order of the list); another one is read page by page.

A bulk action of your own is a `BulkAction` (`name`, `role`, `handler()`) and the `BulkActionHandler` service it names:
`button()` makes its `ActionButton` for the records chosen (a `BulkSelection`: the resource, the ids, the page of the
list), `run()` does it and returns a `BulkResult` to show. A button that runs it on the server has the attributes of the
live action `bulk`; one that leads elsewhere needs no `run()`. The example takes
products off sale this way ([`src/Crud/Doctrine/Maximal/DeactivateProducts.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/DeactivateProducts.php)).

For many records, `$selection->each()` gives the ids one by one as they are read (every match is not gathered first),
and the selection carries the `filters` and `sort` of the list: a job sent to a queue reads the same records again
with `CrudListQuery` - `ids($crud, $filters, $sort)`, `items(...)` (page by page, as they are gone through) or
`page(...)`. The same service serves an export: a controller of your own streaming a CSV of what the list shows for the
filters of its address, with its scopes, search and order - and who may see the list checked. `run()` may also lead
elsewhere, `new BulkResult('Eksport w toku', redirect: $url)` (the progress of the job, the file): its title is the
toast of that page. The selection carries what the job needs to read them as the list did: `parameters` (of the address
of the page - the tenant of `/{tenant}/admin`), and `actor` (the id of who chose them); its handler runs within them,
`$pageParameters->within($message->parameters, fn () => ...)`, so its `DoctrineScope`s narrow as on the page.

A question may ask for more than "yes": `new ConfirmInput('app.invoice.delete_reason')` - a text (`type: 'line'`
for one line, `'number'`, `'date'` - checked on the server too), or a choice with
`choices: ['app.status.open' => 'open', ...]` (`translateChoices: false` for names from the database), required
unless `required: false` - in `DeleteAction(input: ...)`,
`BulkDeleteAction(input: ...)` or the attributes of a button of your own (`...$input->attributes($translator)` next
to `data-controller="confirm"`). What was typed comes with the live action: `RecordDeleting::$input` and
`RecordDeleted::$input` (the reason an audit keeps), `BulkSelection::$input` (the status chosen for the records), and
the `ConfirmationInput` service for a decorator of `RecordDeletion`. The browser could send anything, so the server
checks it before the action is done (`ConfirmInput::accepted()`: required and there, one of the choices) - for a
`DeleteAction`, a `BulkDeleteAction` and a bulk action of your own implementing `AsksForInput`; what is refused is a
toast, and nothing is done.

A delete is done only when it is offered to the one asking: the `role` of a `DeleteAction`, or another action in
its place (`new NoAction('delete')`), holds for the live action that deletes as it does for its button.

The ids chosen come from the browser, so before `run()` the list keeps only those of records it could show: found in
its scopes (a `DoctrineScope`, a tenant, a parameter of the address) and readable by the guards of the resource. A
resource without the `read` operation keeps them as they are. What the action itself may do with each record (update
it, delete it) is for its handler to ask: `RecordGuards::assertAllowed()` with the operation it does, as
`BulkDeleteAction` does through `RecordDeletion`.

### Formats of values

A value is printed by the blocks of the theme (above), or by a `ValueFormat` given in place of the type of its column -
in `columns:` and in `details:` alike:

```php
columns: ['name', 'stock' => new ComponentFormat('App:StockLevel', ['low' => 5])],
```

```twig
{# templates/components/App/StockLevel.html.twig #}
{% props cell, low = 5 %}
<span{% if cell.raw < low %} class="text-danger"{% endif %}>{{ cell.value }}</span>
```

The component gets `cell` (a `ValueView`: `value` ready to print, `raw`, the whole `item`, the `column`, the `crud` and
the `view`, "list" or "detail") and the props. A format of your own is a `ValueFormat` and the `ValueFormatter`
service it names, which returns the HTML. An empty value is always the block `type_empty`.

Before it is printed, and when the Doctrine driver reads a record, a value is made ready by the `ValueNormalizer`
services: the first one (by the priority of its tag) that `supports()` it. The bundle's own come last: plain values,
enums, dates (ISO 8601), `Stringable`, `JsonSerializable`, lists, and an entity as its id. One of yours goes first and
can say what a value is: `new TypedValue('money', '12.50')` is printed by the blocks `type_money_list` / `type_money`,
and `new TypedValue('money', '12.50', new ComponentFormat('App:Money'))` by its format (a format of the column comes
first). As text a `TypedValue` is its value - what a title, a guard or an action sees of it.

A list of values prints each entry by the block `list_entry`; an entry that says what it is (a `TypedValue`: a list of
statuses, of amounts) by `type_<kind>_entry`, else `type_<kind>`. A day without a time - a `date` column of Doctrine -
is a `TypedValue('day', '2024-05-01')`, printed by `type_day` (`crud.value.day`); a moment (`datetime`) by
`type_date`. Blocks of your own for every resource - a `type_money` - go into a template named in
`byfareska_crud.value_themes`, looked into after the resource's own `theme:` and before the bundle's.

### Pages of your own about a record

A page that only shows something - the history of a record, its invoices - is a Twig component and an attribute
(on the component's class, or an empty class of its own):

```php
#[AsCrudPage(resource: 'product', name: 'stats', component: 'App:ProductStats', icon: 'graph-up')]
#[AsTwigComponent('App:ProductStats', template: 'components/App/ProductStats.html.twig')]
final class ProductStats
{
    public string $id = ''; // of the record, as in the address
}
```

The bundle adds the route (`crud_product_stats`, `<path>/{id}/stats`; `<path>/stats` for an `own` resource), the way to
it from the record (details page, preview drawer, edit form, row menu - like a `RouteAction`), and draws the component in
the frame of a page of a record: the layout of the panel, the name of the record, the way back. Its text is
`crud.<resource>.action.<name>`; `role:` narrows it further, `resource: AsCrudPage::EVERY` adds it to every resource
(its component then gets `resource` too), `priority:` orders the pages of a record (as it does the sections of
`#[AsCrudFormSection]` and `#[AsCrudDetailsSection]`, lower first).

`collection: true` makes it a page of the whole resource - an import, a kanban, a report: at `<path>/<name>`
(`/admin/categories/overview`), led to by a button next to "new" on the list, for those who may see the list; its
component gets no id ([`src/Crud/Doctrine/Maximal/CategoryOverview.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/CategoryOverview.php)).

A page that needs a controller - a form of its own, a POST, data of its own - takes the record with `#[MapCrud]`:

```php
#[Route('/admin/categories/{id}/summary', name: 'app_category_summary')]
public function summary(#[MapCrud('category')] CrudContext $category): Response
{
    return $this->render('category/summary.html.twig', ['stats' => $this->stats->of($category->id)]);
}
```

```twig
{% extends '@CrudTheme/crud/record.html.twig' %}
{% set heading = 'crud.category.action.summary'|trans %}
{% block record_content %}…{% endblock %}
```

A controller that changes the record says so: `#[MapCrud('invoice', update: true, action: 'approve')]` asks the
roles and the guards of `Update` (not of `Read`) and the `RecordActionGuard`s of `approve` - a POST approving an
invoice is refused for a paid one as its button is. (A `RunAction` does it without a controller of your own.)

Either way the page is like the bundle's own: only for somebody the resource and its panel are for, a 404 page for a
record that does not exist, the record of the one signed in for an `own` resource, and the panel knows what the page is
of (`crud_context()`, a `CrudContext`): its menu, with the resource lit, even without `_scope` on the route. The
parameters of a prefix (`/{shop}/admin`) are handed on (`PageParameters`, the addresses made on the page) only from a
route of a panel: give a controller of your own under such a prefix `defaults: {_scope: admin}` (`#[Route(...,
defaults: ['_scope' => 'admin'])]`), else the scopes reading `{shop}` get nothing and links of the record miss it.
`record.html.twig` takes nothing passed; `heading` names the page (the name of the record goes next to it), `meta`
changes what goes next to it, the block `record_actions` the buttons. Lead to the page with
`actions: [new RouteAction('summary', 'app_category_summary', 'bar-chart')]`.

### Texts

Translations (`messages` domain, ICU): `crud.<name>.title`, `.new`, `.edit`, `crud.<name>.field.<field>.name`
(`.help`), `crud.<name>.layout.<group>.name`, `crud.<name>.palette.keywords`, `crud.<name>.action.<action>`,
`crud.<name>.bulk.<action>`, and `crud.<name>.toast.created` / `.updated` / `.deleted` in place of the bundle's toasts
for that resource ("The invoice was issued") - given `{id}` and, after a save, the plain values of what was saved
(`'Faktura {number} wystawiona'`). The bundle's own texts are under
`ui.*`, `crud.*` and `panel.*` in Polish and English; override any of them in the application. A resource cannot be
named like a group of them (`action`, `filter`, `toast`... - `AsCrud::RESERVED_NAMES`): its texts would be the bundle's.

### What the bundle does not do, and the way around it

- **Deleting is removing.** The doctrine driver removes the entity. To keep it instead - marked deleted, archived -
  let Doctrine turn the removal into an update (Gedmo `SoftDeleteable`, or an `onFlush` listener of your own that
  schedules an update in place of the deletion) and leave the deleted out of the panel with a `DoctrineScope`
  (`$query->query->andWhere('e.deletedAt IS NULL')`): the bundle's way of deleting stays as it is - the guards,
  `RecordDeleting` and `RecordDeleted`, the transaction. What must happen around every delete (an archive written
  first) is a decorator of `RecordDeletion` (`#[AsDecorator(RecordDeletion::class)]`, calling the one it decorates).
- **An id is one value.** The doctrine driver finds a record by an id of one field (an integer, a string, a Uuid). An
  entity with an id of several fields needs one of a single field to be a resource - or a `CrudDriver` of your own,
  with its own idea of what the text of an id is.
- **The operations are five.** List, read, create, update and delete (`CrudOperation`) are what a source, the roles
  (`roles:`) and the guards (`RecordGuard`) speak of. Anything else - duplicate, approve, restore, send - is an action
  of a record (`RouteAction`, `ComponentAction`, `LiveListAction`, a kind of your own) or a bulk action: `role:`
  narrows who is offered it, a `RecordActionGuard` service says which records it is for (`allowsAction($name,
  $record)`: "approve" only a draft - its button goes, an `AsCrudPage` of that name answers 403), and what runs it
  (the controller, the live action, the use case) checks again with `RecordGuards::assertActionAllowed($name,
  $record)` - a button is only a way to it.

## Panels

The menu of a panel is made of its resources with a `section`, its dashboard and what `NavigationSource` services add
(`NavigationItem`: a route, or `url:` of another site, `target: '_blank'`, a `badge:` - a Closure asked when the menu is
drawn, `attributes:`). A `NavigationExtension` service changes the whole menu for the request, after them: hides an
item, moves one (`$item->with(priority: ...)`), gives one a badge ([`src/Crud/Doctrine/Maximal/CatalogMenu.php`](https://github.com/byfareska/crud-example/blob/main/src/Crud/Doctrine/Maximal/CatalogMenu.php)).
An item goes under another with `parent:` (the route name of that one: "Sales" > "Orders", "Returns" - open while one
of them is lit); `badgeVariant: 'danger'` colours its badge ("5 overdue"); `labelParameters:` are those of the
translation of its label, `translate: false` takes a label as it is (a view a user saved, a name from the database).
The items are asked once a request, whatever asks for them (the menu, the palette, the quick actions).

A component with `#[AsTopbarWidget(priority: 10)]` goes into the top bar - of one panel with `scope:`, for
somebody with `role:`, given `props:`; a `TopbarWidgetSource` adds such components (`TopbarWidget`) when they are known
only at run time. A scope with `role` is hidden
from the switch for anybody without it, and so are its menu, its palette and its resources - whatever their own role.

Who is acting is the `Actor` service (`id()`, `isGranted()`); the default reads Symfony Security. Alias
`Byfareska\Crud\Security\Actor` to a service of your own to change it. To change only which value is the id of a user,
alias `Byfareska\Crud\Security\UserIdMapper`: it returns an `int`, a `string` or a `Stringable` (a Uuid), e.g.
`return $user->getId();` (the default: the user's `getId()`, else its identifier); the `IdMapper` below makes it text.
Without Symfony Security the default `Actor` grants no role at all - a `role` nobody can check is not "everybody".

The user menu of the top bar shows the one signed in as the `PanelAccount` service tells (`panel_account()` in a
template): their name and identifier, the way to sign out (the `logout` of the firewall, its CSRF token in the
address; none offered on a firewall without `logout`) and the way back from `switch_user`. Without SecurityBundle it is
nobody. Alias `Byfareska\Crud\Security\PanelAccount` for a sign-out of a single sign-on or a name read elsewhere.

Ids become text - in addresses, in the palette, for the "dto" driver's new records - by the `IdMapper` service (the
default: as written in JSON, so a Uuid as in its JSON). Alias `Byfareska\Crud\Resource\IdMapper` for ids of a kind of
your own.


## Themes

Every template of the panel - its pages, the components (`Ui:*`, `Crud:*`, `Panel:*`), the blocks values and layouts
are drawn with, the form theme of its forms - belongs to a **theme**, as form themes do in Symfony Forms. The bundle has
three, with nothing but their framework's classes:

| Theme | |
| --- | --- |
| `daisyui` (default) | Tailwind CSS 4 + daisyUI 5: `<dialog class="modal">`, `<details>` menus, `daisyui_5_layout.html.twig` for the forms; a colour theme of daisyUI by `data-theme`, picked in the top bar |
| `bootstrap5` | Bootstrap 5.3: modal, offcanvas, dropdown, `bootstrap_5_layout.html.twig` for the forms; light / dark by `data-bs-theme` |
| `tailwind` | Tailwind CSS 4: native `<dialog>` and `<details>`, `tailwind_2_layout.html.twig` with the theme's classes; dark by `dark:` |

The form theme of the panel's forms and filters comes ahead of the application's global `twig.form_themes`; the form
themes of `byfareska_crud.form_themes` come after it, so their blocks win there - a `choice_widget` of tom-select, a
`money_widget` of your own ([`templates/form/panel_fields.html.twig`](https://github.com/byfareska/crud-example/blob/main/templates/form/panel_fields.html.twig)).

The templates are asked for under `@CrudTheme/...` (`@CrudTheme/crud/list.html.twig`) and found in the theme in use,
then in its parent. The components are found the same way under their own names (`components/Ui/Modal.html.twig`),
ahead of the templates of the application - so a component of the application's own named as one of the bundle's
(`templates/components/Ui/Modal.html.twig`) would never be drawn, anywhere: the container refuses to build then,
naming it. Rename it, or put it into a theme to change the panel's. A theme of the application is a directory with **only the templates it changes**:

```yaml
byfareska_crud:
    theme: acme
    themes:
        acme: { path: '%kernel.project_dir%/templates/crud_theme', parent: bootstrap5 }
```

```twig
{# templates/crud_theme/components/Ui/PageHead.html.twig - replaces the bootstrap5 one #}

{# templates/crud_theme/panel/layout.html.twig - changes one block of the parent's #}
{% extends '@CrudTheme.bootstrap5/panel/layout.html.twig' %}
{% block main_class %}container-xxl{% endblock %}
```

`@CrudTheme.<name>/...` is a template of that theme (and its parents), whatever the theme in use - what a template
extends to change only a part of its parent's. The theme is read when a page is drawn (an env var will do, and
`Themes::use()` changes it for the rest of a request); `crud_theme()` tells a template which one it is.

The list and the form are drawn in blocks, the same in every theme, so a template changes one part and keeps the rest:

| Template | Blocks |
| --- | --- |
| `components/Crud/List.html.twig` | `list_toolbar`, `list_filters`, `list_above_rows`, `list_bulk_bar`, `list_table` (in it `list_columns`, `list_row`, `list_cell`, `list_row_actions`, `list_empty`), `list_footer`, `list_preview` |
| `components/Crud/Form.html.twig` | `form_fillers`, `form_fields`, `form_actions` |
| `components/Panel/Sidebar.html.twig` | `sidebar_brand` (the name, a logo), `sidebar_sections`, `sidebar_footer` |
| `panel/dashboard.html.twig` | `dashboard_head`, `dashboard_actions` (more buttons next to its own), `dashboard_grid` |
| `panel/layout.html.twig` | `page_title`, `content`, `main_class`, `topbar_start`, `topbar_actions`, `user_menu_items` (entries of the user's menu above "sign out") - filled by any page extending it |

```twig
{# templates/crud_theme/components/Crud/List.html.twig - every list of the theme "acme" #}
{% extends '@CrudTheme.bootstrap5/components/Crud/List.html.twig' %}
{% block list_footer %}{{ parent() }}<p class="small">…</p>{% endblock %}
```

For one resource only, its list (or form) of its own (`listComponent:`, see "The list and the form of a resource's
own") has a template of its own extending `@CrudTheme/components/Crud/List.html.twig` - the theme in use - with the
blocks it changes; it needs no code: `#[AsLiveComponent('App:ProductList', template: 'components/App/ProductList.html.twig')]`
on an empty class extending `CrudList` ([`templates/components/App/ProductList.html.twig`](https://github.com/byfareska/crud-example/blob/main/templates/components/App/ProductList.html.twig)). Inside a block the
variables of the list are there (`crud`, `result`, `this`; in a row `item`, `id`; in a cell `column`, `value`).

### A theme per panel

Each panel can have its own theme; with `{theme}` in the entries, a page loads the one built for it:

```yaml
byfareska_crud:
    theme: bootstrap5
    entries: [panel_{theme}, app]
    scopes:
        panel: { icon: house-door } # bootstrap5: panel_bootstrap5, app
        admin: { icon: shield-lock, role: ROLE_ADMIN, theme: tailwind, entries: [charts] } # panel_tailwind, app, charts
```

A page is drawn in the theme of its panel (its route default `_scope`) and loads its entries (`panel_entries()`).
Turbo tracks them, so going from one panel to another with other entries loads the whole page. The live endpoint
(`/_components/...`) has no panel in its address, so the component re-rendered tells it: the components of the bundle
implement `ScopedComponent` (a list, a form and a delete button know their resource's panel, the palette and the
dashboard dialogs know theirs). A live dashboard widget gets the same by using `DashboardWidgetProps` and declaring
`implements ScopedComponent`; a live component of your own does it with `panelScope()`.

A layout of your own (`layout:` of the bundle or of a panel) extends `@CrudTheme/panel/base.html.twig`, which marks
the panel of the page (`<body data-scope>`, what the command palette searches in), and has what the panel's
controllers look for: `<twig:Panel:CommandPaletteModal />`, `<twig:Ui:Toast id="panel-toast" data-turbo-permanent />`,
`<twig:Ui:ConfirmDialog />` and the toasts of the session (`app.flashes('toast')`) - see `@CrudTheme/panel/layout.html.twig`.

The theme comes from a `ThemeSelector`, by default `ScopeThemeSelector` (the panel's theme, else `theme`). To choose
differently, e.g. a theme the user picked, alias the interface to a service of your own.

Every icon of the panel is drawn by `crud_icon(name, class, attributes)` with the theme's `crud/icon.html.twig`
(Bootstrap Icons by name, `<i class="bi bi-…">`): a theme of another set - an SVG sprite, Material Symbols - replaces
that one file and maps the names.

The application's own templates stay theme-neutral when they use the `Ui:*` components (`Ui:Widget`, `Ui:WidgetStat`,
`Ui:WidgetList`, `Ui:PageHead`...) - see the widgets of [the example](https://github.com/byfareska/crud-example/tree/main/src/Dashboard).

### The controllers in any theme

The Stimulus controllers know no framework. Dialogs, drawers and menus go through `@byfareska/crud`'s overlay functions
(`openOverlay`, `closeOverlay`, `toggleOverlay`, `closeMenus`, `anyOverlayOpen`): Bootstrap's components once
`useBootstrap()` is called, native `<dialog>` / `<details data-dropdown>` otherwise. Either way the element dispatches
`overlay:show`, `overlay:shown` and `overlay:hidden`, and `data-overlay-open="<id>"` on anything opens that dialog on a
click. What a theme marks for them:

| Hook | |
| --- | --- |
| `data-dashboard-edit`, `data-dashboard-remove` | the controls of a widget shown in edit mode, its "remove" (Delete) |
| `.crud-fieldset` | a group of the layout the `show-if` controller hides |
| `hidden` | tabs, toasts and edit controls are shown and hidden by the attribute, never by a class alone |

#### Options, events and values

`registerControllers(app, { except: ['dashboard-grid'], prefix: 'crud--', csrf: false })`: `except` leaves controllers
out (to register one of yours under its name), `prefix` goes before every name (the bundle's templates use the names
without one), `csrf: false` skips the CSRF listener. An application that registers controllers one by one from
`controllers` calls `installCsrfProtection()` itself. gridstack and vanilla-calendar-pro are loaded only when a
dashboard or a date field is on the page.

Events: `tabs:change` (detail `name`, `tab`, `pane`), `confirm:accepted` / `confirm:cancelled`, `theme:change` on
window (detail `theme`, `mode`). Before any dialog or drawer closes, a cancellable `overlay:hide` is dispatched -
`event.preventDefault()` keeps it open (unsaved changes). Another kind of drawer plugs in with
`registerOverlayAdapter({ matches, open, close, isOpen })`.

Values:
- `tabs`: `url` (false leaves `?tab` alone), `prop` (`tab`).
- `theme`: `theme#set` with `data-theme-theme-param="light|dark|auto"`, `storage-key` (`panel-theme`), `auto` (follow
  the system while nothing is saved), `modes` (`["light", "dark", "dim"]`: schemes of your own, toggled in turn),
  `dark` (those of them that are dark: `data-color-scheme="dark"` on `<html>`, which the charts go by - the block
  `theme_dark_schemes` of the base template says it before the first paint), `initial` (the scheme of the user's
  profile, while the browser has none saved); `theme:change` on window tells every change, to save it there.
- `date-range`: `first-weekday` (0 Sunday; default from the locale), `breakpoint` (640).
- `dashboard-grid`: `columns` (12, set from `dashboard.columns`), `cell-height` (80), `mobile-breakpoint` (768),
  `save-delay` (400).
- `command-palette`: `storage-key`.
- `toast`: `max` (5), `variants`; `toast:show` takes `variant`: `success` (default), `danger`, `warning`, `info`.
- `flash`: `variant`.

`data-show-if` also knows `field!=a b`, `field:filled`, `field:empty` and `||` between groups
(`kind=email; email:filled || kind=sms`). Checkbox lists (`…[field][]`) and `<select multiple>` match when any chosen
value is listed. When a tab pane is hidden this way, its tab button is hidden instead; if it was the active tab, the
first tab still shown opens.

## Dashboards

A panel with `dashboard: true` has one at "/" of its prefix (its board is named like the panel). More of them - "Sales",
"Warehouse" - are its `boards`, each at a path of its own, with its own layouts and widgets (`AsDashboardWidget::$boards`
names them), in the menu after the first one, for those its `role:` (and the panel's) is for:

```yaml
byfareska_crud:
    scopes:
        admin:
            boards:
                warehouse: { label: app.board.warehouse, icon: box-seam } # /admin/warehouse; path: to say another
```

Its `label` is the heading of its page too; `template:` gives it a page of its own (it may extend
`@CrudTheme/panel/dashboard.html.twig` and change a block of it - a banner of numbers over the grid). The grid takes
`data-dashboard-grid-*-value` attributes (columns, cell height, margin...), and a listener of `dashboard-grid:options`
on its element changes any other option of gridstack before the grid is made.

A widget is a Twig (or live) component with `#[AsDashboardWidget]`, found by autoconfiguration:

```php
#[AsDashboardWidget(
    key: 'app.catalog',
    title: 'app.widget.catalog.title', description: 'app.widget.catalog.description',
    icon: 'box-seam', size: new WidgetSize(3, 2),
    role: 'ROLE_ADMIN', default: true, boards: ['admin'], lazy: false,
)]
#[AsTwigComponent('App:CatalogWidget', template: 'components/App/CatalogWidget.html.twig')]
final class CatalogWidget
{
    use DashboardWidgetProps; // placement, board, settings
}
```

The widget is rendered by the name of its `#[AsTwigComponent]` (give it one, or `component:` to the attribute).
`default: true` puts it on the layout everybody starts with; `settings: [new WidgetSetting(...)]` gives it a dialog;
`boards` limits it to some panels. `dashboard.disabled_widgets` takes widgets out by key - the bundle's own as well.
What the whole dashboard is looked at for - a period, a branch - is the `DashboardContext` service: the parameters
`context` of its address (`?context[period]=q3`, set by a GET form of yours in the block `dashboard_filters` of its
head), known to a widget loaded after the page too (by the address its request comes from).
A setting is a choice, several of them, a text, a number (`WidgetSettingType::Number` with `min:`, `max:`, `step:` -
the threshold of an alert), a switch (`Toggle`: "1" or ""), a day (`Date`, Y-m-d) or a colour (`Color`, #rrggbb):
whatever is sent, the widget gets a value of its kind or the default.

The arguments of an attribute are constants, so choices that come from a service are named instead:
`new WidgetSetting('zone', ..., choicesFrom: 'panel.timezones')` takes them from the `WidgetChoices` service with
`#[AsTaggedItem('panel.timezones')]` (the bundle has `panel.timezones` and `panel.quick_actions`). Widgets known only at
run time (one per record, per resource...) come from a `DashboardWidgetSource` yielding `DashboardWidget`s. Users move, resize, add and remove widgets; their layouts are kept by
`DashboardLayouts` (`doctrine`, `cache` or your own).

- **Who may do what** is the `DashboardPolicy` service (the bundle's `RoleDashboardPolicy`: everybody signed in
  arranges their own, `share_role` stores theirs for everybody). Alias it for a default layout per team or role
  (`fallbackOwners()`: `['team:5', null]` - the team's, else everybody's; `shareOwner()`: where a share is stored) or a
  dashboard nobody rearranges (`mayEdit()` false). `DashboardLayoutSaved` and `DashboardLayoutShared` are dispatched.
- **The layouts of a panel per shop are apart**: they are kept under the `DashboardKey` service's key - the panel and
  the parameters of its address (`admin?shop=5`, for `prefix: /{shop}/admin`). Alias it for another context: the
  tenant of the user, a team.
- **A widget left out for a while keeps its place**: a layout saved while a widget is not there (a filter, a role, a
  feature flag) keeps that one as it was, settings and all.
- **A shared layout keeps the widgets, not what is personal in them**: a setting `personal: true` (by default a
  `Hidden` one - the text of a note) goes as its default to the others.
- **Settings**: `maxLength:` for a text, `validator: FeedUrl::class` - a `WidgetSettingValidator` service checking the
  value further when it is set; choices that depend on the dashboard come from a `BoardWidgetChoices`
  (`choicesFor(string $board)`).
- **Widgets left out for a request** - a tenant without the clock, a feature flag - by a `DashboardWidgetFilter`
  (`allows(DashboardWidget $widget, string $board)`); `disabled_widgets` leaves them out for everybody.
- **The grid** has `dashboard.columns` columns (12); the sizes a widget declares are cells of it, the presets (s, m, l,
  xl) are as wide as on 12.

## Command palette

The palette offers the pages of the panel (`CommandPaletteSource`: `PaletteEntry`), commands (`>` in front) and records
found by typing (`CommandPaletteRecordSource`; for resources: through their list's search filter).

**How it searches is one service**, `PaletteSearch`:

```php
interface PaletteSearch
{
    /** @return list<string> keys of the matching items, best first */
    public function search(string $query, array $items, string $locale, int $limit): array;
}
```

The default, `TextPaletteSearch`, matches letters (without accents). To search by meaning, write a service and name it
in the configuration - nothing else changes:

```php
final readonly class EmbeddingPaletteSearch implements IndexedPaletteSearch
{
    public function __construct(private Embeddings $embeddings, private VectorStore $store) {}

    public function index(array $items, string $locale): int
    {
        foreach ($items as $key => $item) {
            $this->store->put("$locale:$key", $this->embeddings->embed("$item->name. $item->description " . implode(', ', $item->keywords)));
        }

        return count($items);
    }

    public function search(string $query, array $items, string $locale, int $limit): array
    {
        $nearest = $this->store->nearest($this->embeddings->embed($query), prefix: "$locale:", limit: $limit);
        // Only what this user may see in this panel: $items is already filtered.
        return array_values(array_filter($nearest, static fn (string $key): bool => isset($items[$key])));
    }
}
```

```yaml
byfareska_crud:
    command_palette:
        search: App\Palette\EmbeddingPaletteSearch
```

A search that keeps an index implements `IndexedPaletteSearch`; fill it with `bin/console byfareska:crud:command-palette:index`
after a deploy (the entries come from the code).

What is listed of what was found is the `PaletteRanker` service: the bundle's `BalancedPaletteRanker` keeps at least
half of the places for the entries, so records do not push the pages out; alias it to rank otherwise.
`command_palette.limit` and `records_per_source` say how many. A source of records that implements
`ScopedRecordSource` is told the panel (`searchIn($query, $limit, $scope)`) and searches only there - the bundle's
does. An entry of your own becomes a tile of the quick actions widget with `quickAction: true` (besides the entries
`….create`). The recent items are kept in the browser under a key of the one signed in.

## Tests

```sh
composer install && vendor/bin/phpunit
```

The same and the linters through `make` (on the PHP of the host, with intl and pdo_sqlite, as CI does; `RUN=...`
runs them in a container):

```sh
make test
make lint # deptrac, phpcs, PHPStan (level 10), Rector (dry run), composer validate, composer audit
make lint fix=1 # Rector and phpcbf fix what they can first
```

`deptrac.yaml` keeps the optional dependencies apart: Doctrine is used only by its integration (`Driver/Doctrine`,
`Chart/Sql`, the Doctrine dashboard storage), Symfony Security only by `SecurityActor` and its `UserIdMapper`, and
`CrudBundle` registers them; the rest of the bundle must work without either.

The [example application](https://github.com/byfareska/crud-example) has functional tests of the whole panel
(both drivers, the dashboard, the palette).
