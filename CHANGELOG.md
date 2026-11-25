# Changelog

## Unreleased

## v0.4.1

Panels work with the newest `symfony/ux-live-component`.

### Fixed

- With `symfony/ux-live-component` 2.36.3 / 3.8.1 or later, every live request of a panel answered 404 ("Component
  ... is only served by route ux_live_component"): those versions answer a component without a route of its own only
  at LiveComponent's route. The live route of a panel says it is that route under another address
  (`_canonical_route: ux_live_component`).

## v0.4.0

A security release: what a user may not see stays out of the forms, searches, sorting and filters, relation searches
and choices keep to the scopes of the related resource, the live components of a panel are answered only under its
prefix and only to pages of its own origin, copies, actions and deletes keep to the roles and guards of their actions,
the guessed form of an `own` resource leaves out what looks privileged, and the media library and file fields refuse
files they were not given. Read Upgrading: most changes only make the bundle stricter, some can be noticed.

### Upgrading

- **`hidden` hides a property everywhere.** `AsCrud::$hidden` now also keeps it out of the list columns
  (`$crud->columns`), what a record is named by (`title`, also in the palette and relation labels), the guessed
  Doctrine add and edit forms, `?with[...]` prefills and `?from=` copies, and the Doctrine `search`, `sortable` and
  `filters` (by their name or by the field they compare). A form the application names (`DoctrineForm(fields: [...])`,
  a form type) still edits it; to remove it there, use a `CrudFormExtension`. A hidden property that is a required
  constructor argument makes the guessed add form fail: name the fields or give a form type.
- If every field of `search` is hidden (and there is no `searchWith`), a typed text finds nothing. `?sort=` naming a
  hidden field is ignored; the default `sort` may still use it. `AbstractCrudDriver::listFormBuilder()` leaves hidden
  fields out of `sortable`; a driver of your own should leave hidden filters out too (`AsCrud::hides()`).
- Taking a column away in a `CrudDefinitionExtension` does not hide the property (details, forms and searches still
  have it). To hide it per user, `return $crud->hiding('margin')`; the documented example changed accordingly.
- **Relations of the guessed forms keep to the scopes of the related resource** (`DoctrineScope`), for the choices and
  for the submitted ids, and so do the guessed relation filters: an id out of the scopes is "The selected choice is
  invalid". The value a record already has stays valid. Entities without a resource are narrowed by the `EVERY`
  scopes. A field or filter with a `query_builder` of the application keeps it.
- **Relation searches** (symfony/ux-autocomplete) of a user who may not see the list of the related resource look only
  in the fields its entities are named by (its `title:`, else its first column), not in its `search:`. An entity
  without a resource, or one without a title of text, is found only by its exact id. Hidden fields are never searched.
  Give the related resource a `title:` or the user its list.
- A relation search of a form answers only when the user's add form, or the edit form of that record, has the field,
  enabled: removed or disabled by a `CrudFormExtension`, left out of `fields`, or hidden by a `When`, it is refused.
- A relation `FieldFilter` with options of its own choices (`RelationFilterField::OWN_CHOICES`: `query_builder`,
  `choices`, `choice_loader`, `choice_filter`, `choice_label`, `choice_value`, `options_as_html`) is a plain choice, not
  a relation search of the bundle. A filter that set only `choice_label` loses typing and loads every entity in scope.
- Relation labels take the title of the resource of the current panel first.
- **Live components of a panel** (`ScopedComponent`, or anything using `DashboardWidgetProps`) are answered only under
  the live route of that panel (`<prefix>/_crud/...`): 403 under another panel's prefix, and at `/_components/...` when
  the panel's routes are imported (`type: byfareska_crud`). Hard-coded `/_components/...` addresses of the bundle's
  components stop working.
- A component of a panel drawn on another panel's page, or on a page of your own, sends its live requests to its own
  panel's prefix: across firewalls it no longer works, and a page of your own outside a prefix with parameters
  (`/{shop}/admin`) needs those route parameters and the `_scope` default.
- Live dashboard widgets using `DashboardWidgetProps` are bound to the panel of their board even without
  `implements ScopedComponent`.
- The command palette modal is no longer `data-turbo-permanent` (drop it in an override of `CommandPaletteModal`). A
  palette with `scope: ''` shows the panel of the request instead of every panel.
- In live requests under a panel's prefix, `PageParameters` and the router context take the parameters of the prefix
  from the requested address, never from `X-Live-Url`; the header adds only the path parameters of a page of the same
  panel with the same prefix.
- `Crud:Run` goes back to `X-Live-Url` or the `Referer` only when it is a path of the application (or the same host);
  otherwise, and when neither is sent, to `CrudReturn::url()` of the record.
- **The media library** deletes only files it would list (an entry of its library, of a kind the field accepts, in the
  storage in view), none in a storage with `upload: false`, and a file in use only after the server asked about it.
  `FileRemover::remove()` throws `InvalidArgumentException` for a file the library of its storage does not know (no
  `crud_file` row, any file of a `library: none` storage): delete those through the storage. `StorageFileLibrary::find()`
  returns null for the paths of image sizes and for references of another storage.
- A file picked from the library or sent by its signature is refused when the user lacks the `role` of its storage, when
  it is bigger than the field's `max_size`, or when it is SVG, HTML, XML or a script and the field does not name its MIME
  type - also for `accept: []`. A path ending in `.svg`, `.html`, `.xml`, `.js`, `.php`... needs its MIME type named in
  `accept` (`.svg` or `image/*` is not enough).
- A refused value of a file field shows again only the files of the record and those validly signed.
- An upload of a kind without an extension of its own is stored as `.bin`.
- The file JSON has a `link` key and Twig a `crud_file_link()` function: a file a browser would run (SVG, HTML...) is
  linked only through the signed download of a `url: signed` storage. Theme overrides of `crud_file_item` or
  `file_values` should link `file.link` / `crud_file_link(value)`, not `url`.
- The public addresses Flysystem gives (a storage without `public_url`) have their path segments percent-encoded.
- Constructors: `FileFieldTransformer` and `FilesExtension` take `BrowserFiles`; `ScopeOfComponent` takes
  `ComponentPanel`; `ChoiceRelationFormField` is `(?DoctrineRecordLabels $labels = null)`; optional arguments at the end
  of `RelationAutocompleter` (`AutocompletedFormFields`), `DoctrineRecordLabels` (`RequestStack`), `CommandPalette`
  (`Scopes`), `CrudRun` (`CrudReturn`), `NoteWidget` (`LivePanelGuard`) and `PanelLiveUrls` (`ComponentPanel`, no longer
  `readonly`). Only code that builds these services by hand is affected.
- **`?from=<id>` copies a record only through its `DuplicateAction`**: for a user it is offered to for that record
  (its `role`, a `RecordActionGuard` of `duplicate`), besides Create, Read and the guards of reading it. A resource
  without a `DuplicateAction` answers 403 to `?from=`; declare `new DuplicateAction()` to keep it.
- A `RunAction` with `update: true` (the default), or another action whose handler says it updates, is not offered
  when `roles['update']` or `refusing(Update)` takes editing away, and running it answers 403. Give `update: false` to
  an action that people who may not edit should still run.
- `Crud:Run` on an `own` resource knows the record by the `OwnRecordResolver`, as the page and `Crud:Form` do.
- **A `BulkDeleteAction` without a `role` of its own takes the `role` of the resource's `DeleteAction`**: with
  `new DeleteAction(role: 'ROLE_ADMIN')` in `actions`, only users offered that action get the bulk delete button, and
  for the others running it does nothing. With several `DeleteAction`s, the role of one of them is enough; one without
  a role, the built-in delete, or no `DeleteAction` at all (`new NoAction('delete')`: deleting in bulk only) leaves it
  to everybody the resource is for. A `BulkDeleteAction` with a role keeps its own: give it one to restore the old
  behavior.
- **Deleting a record asks the `RecordActionGuard`s of the action `delete`**, whatever the `DeleteAction` is named: the
  delete button is not offered for a record they refuse (shown disabled with an `ExplainsRefusal`), and
  `RecordDeletion` refuses it with a 403, so the live delete actions refuse it and a bulk delete leaves it and deletes
  the others, counting it in the toast. A `RecordActionGuard` that refuses every action it doesn't know now refuses
  deleting too. `VoterActionGuard` doesn't ask `DELETE` (`VoterRecordGuard` does); an `attributeOf()` of your own that
  returns one for `delete` now asks it.
- Routes fail to load when the prefix or host of a panel's import has a parameter named `crud`, `id`, `page`, `name`
  or `board`, or one starting with `_` other than `_locale` and `_format`: the address would choose the resource, the
  record or the page. The bundle decorates `routing.loader`; panel routes carry the option `byfareska_crud_variables`.
- A board named like another panel's board, or like a panel only resources name, throws when the routes load.
- A custom `PathGenerator` must give a path ending in `.<the extension it is given>`, of at most 255 bytes, with no
  empty part, no part starting with `.` and not in the directory of the image sizes; otherwise the upload fails
  (`FileStorageFailed`, logged, "file.refused.failed"). `FileUploader` takes an optional `ImageVariants` (autowired).
- A rich text field that names `image/svg+xml` (or another type a browser runs) offers those files in its image dialog,
  never in its link dialog; one that accepts nothing else gets no library for links. An editor adapter of your own
  should link at `file.link` (null for SVG, HTML...), not `file.url`.
- `crud_attrs()` (RecordRow, action buttons, icons) leaves out an attribute whose name has white space, a quote, `<`,
  `>`, `/`, `=`, a control character or invalid UTF-8.
- Twig names `components/...` or `@CrudTheme...` with a `..` segment are not found by the panel's loader.
- On the extra boards of a panel, the quick-actions widget offers that panel's actions.
- `CrudRun` takes an optional `?OwnRecords` at the end of its constructor. New: `RecordActions::duplicateAction()`.
- **Requests from other sites.** The panel's live route, its file upload and the dashboard's changes answer 403 when
  `Sec-Fetch-Site` is `cross-site`, `same-site` (another subdomain) or `none`; the live route also needs
  `X-Requested-With: XMLHttpRequest` (400 without it; the scripts of LiveComponent 2.20 and later send it), a child
  `tag` that is an element name, and accepts only `GET` and `POST` (405 for `HEAD`). A component served from another
  origin or subdomain than its page stops working. Routes carry the new default `_crud_same_origin`.
- **Panel responses carry `X-Frame-Options: SAMEORIGIN`** (`byfareska_crud.frame_options`: `SAMEORIGIN`, `DENY` or
  `false`), unless the response has its own `X-Frame-Options` or a `frame-ancestors`. A site that frames the panel
  from another origin needs `frame_options: false` or a header of its own.
- On a list embedded with `fixedFilters`, the delete, the actions and the preview of a row take only records those
  filters match. A `LiveListAction` of your own should go through `CrudList::isInList()` and `RecordChanges`.
- A `RunAction` for users who may not read records reads its record in the scopes: 404 for an id out of them. Guards
  and listeners get the whole item instead of `[]`.
- A list keeps at most 1000 records chosen by hand (`CrudList::MAX_SELECTED`); more is "select all N matching". The
  command palette looks for at most 200 characters (`CommandPalette::MAX_QUERY`).
- **The guessed form of an `own` resource leaves out what looks privileged** (`AsCrud::PRIVILEGED`: roles,
  permissions, admin, enabled, active, verified, approved, banned, locked, tenant, owner, team, organization, rate,
  balance, credit, salary, discount, plan, quota... as words of the name) and relations to a Symfony Security user.
  List one in `DoctrineSource(fields: [...])` to keep it. Other resources are unchanged: whoever may edit a `User` may
  give it `ROLE_ADMIN` (see "What a form changes").
- A Doctrine `?from=` copy leaves out the related entities the user's scopes leave out now.
- An image whose content `getimagesize()` can't read gets sizes only when fileinfo tells by its content that it is an
  image the resizer takes; its name no longer counts. SVG never reaches the resizer.
- A dashboard owner id starting with `*` is stored with one more `*` in front: such a user starts again from the
  shared layout.
- `FileReferenceSigner` throws with an empty `kernel.secret`.

### Added

- `AsCrud::hiding()` and `AsCrud::showing()`: a `CrudDefinitionExtension` hides a property for the request, or shows
  one declared hidden, and keeps the rest of `hidden` as it is (`with(hidden: [...])` replaces the whole list).
- `AsCrud::hides()`, `SortField`, `ComponentPanel`, `LivePanelGuard`, `LocalUrl`, `AutocompletedFormFields`,
  `Scopes::panelOf()`, `Scope::liveRouteOf()`, `PanelLiveUrls::hasLiveRoute()`, `DoctrineRelations::choices()`,
  `DoctrineRecordLabels::titledBy()`, `UploadProfile::isActive()`, `StoredFile::isActive()`, `BrowserFiles::link()` and
  the Twig function `crud_file_link()`.
- `SameOriginGuard`, `PanelFrameOptions`, `CrudList::isInList()`, `AsCrud::PRIVILEGED`, `AsCrud::looksPrivileged()`,
  `DoctrineRelations::within()`, the option `frame_options`.
- docs/files.md: serving uploaded files (a public storage on the panel's origin, nginx and Apache settings, the
  headers of an `offload` location).

### Security

- The media library deleted any path of the storage in view, also in storages only to pick from and files in use
  (the confirmation was the browser's): anybody who could open a file field with a library could delete any object of
  that storage.
- A refused value of a file field described and signed every reference the browser sent: anybody who could submit a
  form with a file field learnt whether any path existed (its size, type, name), got a signature to attach that file to
  their record and, for a `url: signed` storage, a signed download address.
- An SVG or HTML file accepted by a field (`accept: ['image/svg+xml']`) on a public storage served from the panel's
  origin was linked to open in a new tab, where its scripts ran with the session of whoever clicked it.
- The live components of a panel were answered under any prefix and at `/_components`: a panel protected by
  `access_control` or a firewall on its prefix alone (as the documentation allowed) could be listed, edited and
  deleted from through the address of another panel or the global one.
- The `X-Live-Url` header chose the prefix parameters (`/{shop}/admin`) of a live request: what the firewall and
  `access_control` saw (`acme`) was not what the scopes and guards got (`globex`).
- `hidden` properties were in the guessed add and edit forms: any user who could edit a record read and changed them.
- Properties hidden per request were still searched, sorted and filtered by, which told their values a character at
  a time; so did the relation search, in the `search:` fields (or every text) of a related resource the user could not
  list.
- A submitted relation id was not checked against the scopes of the related resource: a user could link a record to an
  entity of another tenant and then see its name.
- A relation search of a form answered for a field the user's form did not have (removed or disabled by a
  `CrudFormExtension`), and one of a filter with the application's own choices ignored them (and `options_as_html`).
- `?from=<id>` copied a record into the add form for any resource and user that may create and read, also where no
  `DuplicateAction` is offered (its `role`, a `RecordActionGuard`), showing values its details do not.
- A `RunAction` that updates the record (the default) was offered to users whose editing `roles` or `refusing()` took
  away: they could still approve, archive... what they could not edit.
- A parameter of a panel's prefix or host named like a default of its routes (`/{id}/admin`, `{crud}.example.com`)
  chose the record, resource or page from the address while `access_control` saw another path.
- A `PathGenerator` of the application was written to as it said: `..`, an absolute path, `.htaccess`, another
  extension, a path among the image sizes.
- The link dialog of rich text linked an SVG or HTML file a field accepted at its public address, where its scripts
  ran on the panel's origin.
- `crud_attrs()` let an attribute name with a space or `=` (from record data) start an attribute of its own.
- The `role` of a `DeleteAction` only took away its button: a `BulkDeleteAction` without a role of its own deleted
  for everybody who may delete, and a `RecordActionGuard` of `delete` was asked nowhere, so a record it refused was
  deleted by the row, the page or in bulk.
- The forms of the panel have no CSRF token and LiveComponent before 2.36 / 3.1 checked only the CORS-safelisted
  `Accept` header: with a cookie that is not `SameSite=Lax` (or from a sibling subdomain), another site could save,
  delete and run actions in the name of a signed-in user. A `HEAD` request ran the `onUpdated` hooks of a component
  (the note widget saved its text); a child `tag` sent by the browser was printed as HTML.
- The panel could be framed by any site, and its one-click actions clicked unseen (clickjacking).
- A live action of a list embedded with `fixedFilters` (delete, run, preview) took the id of any record of the
  resource; a `RunAction` of a user who may not read records took an id out of the scopes, another tenant's too.
- The guessed form of an `own` resource ("my account") let a user set their own `roles`, `enabled`, `verified`,
  `tenant`, `team`, rate... and the relation that made the record theirs.
- A `?from=` copy kept related entities the user's scopes no longer allow (access taken away since).
- A file named like an image but holding SVG or MVG was handed to Imagick to resize, which reads what such a file
  points to (local files, URLs).
- A user whose id is `*` read, changed and deleted the dashboard layout shared by everybody.
- Any number of hand-chosen ids in a list cost a query each on a bulk action; any length of text was matched against
  every entry of the command palette.

### Fixed

- `Crud:Run` redirected back to the `X-Live-Url` or `Referer` header without checking it is an address of the
  application.
- The address of a stored file with `%23` or `%3F` in its name did not lead back to the file.
- A filter of the relation search of a property hidden for the user was still answered by its signed address.
- Two panels with a board of the same name: the route of one and the role and panel of the other.
- The quick-actions widget on an extra board of a panel offered none of its actions.
- `Crud:Run` on an `own` resource with an `OwnRecordResolver` refused every action of the page.
- `ThemeLoader` looked `components/...` names with `..` up outside the directory of the theme.

## v0.3.0

Translations of the application win over the default texts, enums that translate themselves are printed translated,
and a `RelationChoiceLabel` names the entity everywhere.

### Upgrading

- A related entity is named in the cells of lists and in the details the same way as in the choices of forms and
  filters (`DoctrineRecordLabels`): a `RelationChoiceLabel`, then the title of its resource, then `__toString()`. An
  entity with `__toString()` whose resource has a `title:` now shows that title in lists and details ("Ewa Lisowska"
  rather than "Ewa Lisowska (1187234)"). To keep `__toString()` there, write a `RelationChoiceLabel` that returns it.
  `DoctrineEntityNormalizer` runs at priority -95 instead of -200, before `PlainValueNormalizer` (-100).
- An enum that implements `TranslatableInterface` is a `TypedValue('enum', TranslatedValue)` in list items and records
  (`RecordView::$item`, guards, layout conditions), not its value as a string. It prints as its translation and
  JSON-encodes to its value. Code that compared `$item['status'] === 'paid'` should compare
  `$item['status']->value->value`, or `json_encode()` it. Enums without `TranslatableInterface` are unchanged.
- The tags of the bundle's interfaces (`RelationChoiceLabel`, `LayoutCondition`, `CrudDriver`, `ScopeExtension`, ...)
  are added by `CrudBundle::build()`, no longer by `#[AutoconfigureTag]` on the interfaces. Nothing to change for an
  autoconfigured service. A service that also has the tag set by hand keeps working.
- The translation catalogues cached by v0.2.0 are not read again: their file names change (`cache_vary` of the
  translator), so nothing in `var/cache/<env>/translations` has to be cleared by hand.

### Added

- `TranslatableEnumNormalizer` and `TranslatedValue`: an enum that translates itself is printed by its `trans()` in the
  locale of the request, in lists, details and record titles; blocks `type_enum*` print it differently.

### Fixed

- The texts made of the names of the resources (`CrudDefaultTexts`) won over the application's translations
  ("Customers" instead of "Klienci"): FrameworkBundle's translator added every translation file before them. They are
  now the first entry of the translator's `resource_files` for their locales, so any translation file wins, as
  documented.
- A Doctrine field with `enumType` reached the value normalizers as the value Doctrine stores (`"active"`), not as the
  enum, so no normalizer of the application saw the enum. Lists of enums (`simple_array`, `json`) too.
- A `RelationChoiceLabel` of the application, autoconfigured, got no tag and was never asked: `#[AutoconfigureTag]` on
  an interface is read only when the interface's directory is loaded as services, and the bundle excludes
  `Autocomplete/` and `Layout/`. The same for `LayoutCondition`. All 30 interfaces with a tag are now registered in
  `CrudBundle::build()`.
- A relation in a cell of a list or in the details was named by `__toString()`, not by the `RelationChoiceLabel` or the
  title that names it in forms and filters.
- `RouteAction::$fieldParameters` put the text of a relation (its name) into the address instead of its id; a
  translated enum gives its value.

## v0.2.0

A panel works with the bundle, one route import and `#[AsCrud]` on Doctrine entities alone: no configuration, no
build of the application's own, no form types, no `__toString()`.

### Upgrading

- `byfareska_crud.assets` is `auto` by default, no longer `reprise`. Without `entries` the panel uses its prebuilt
  assets, or the application's AssetMapper when it has one with StimulusBundle. With `entries` it uses the tool that is
  installed, in this order: Symfony Reprise, Webpack Encore, AssetMapper. To keep Reprise whatever is installed, set
  `byfareska_crud.assets: reprise`.
- A Doctrine relation is normalized to `TypedValue('relation', RecordRef)` instead of the id as a string. As text it is
  the name of the record; in JSON (live props, addresses, the API) it is still the id. Code that read the normalized
  value of a relation as its id should use `RecordRef::$id`.
- Relation searches of `symfony/ux-autocomplete` are answered only at the address of the panel,
  `<prefix>/_crud/autocomplete/{alias}` (route `crud_autocomplete_<panel>`). The global `/autocomplete/{alias}` and
  the address of another panel are refused (401 / 403).
- A `RelationChoiceLabel` of the application now names the entity everywhere it is shown as a relation (lists,
  details, choices of forms and filters), not only in the autocomplete.
- `@byfareska/crud` no longer has `gridstack` and `vanilla-calendar-pro` as peer dependencies: the bundle ships its own
  copies in `assets/vendor`, together with the stylesheets of Bootstrap Icons, gridstack, the calendar and Tom Select.
  The application can drop these packages.
- The toasts draw their icons with `crud_icon()` instead of `bi-*` classes. A toast template of your own keeps working.
- `Scope::homeRoute()` returns `string` (it is never null any more).

### Added

- `byfareska_crud.assets: builtin`: the prebuilt `public/panel/panel.css` and `panel.js`, installed by
  `assets:install` as `/bundles/crud/panel/` and versioned by a hash of the file. They hold the daisyui and tailwind
  themes (every daisyUI colour theme), Bootstrap Icons, gridstack, the calendar, Tom Select, Stimulus, the controllers
  of the bundle, the live controller of UX Live Component 3.7 and the chart controller of UX Chart.js. The sources are
  in `build/panel` (`npm ci && npm run build`). The `bootstrap5` theme has no prebuilt Bootstrap stylesheet.
- AssetMapper with nothing to configure: in `auto` mode the panel's script (`assets/panel.importmap.js`) runs through
  the application's importmap, with its Symfony UX controllers and their versions. The entry and the `@byfareska/crud`
  mapping are added in memory (`PanelImportMapConfigReader`) and never written to `importmap.php`, and
  `asset-map:compile` includes them.
- One import per theme for an application's own build: `@byfareska/crud/styles/panel.daisyui.css`,
  `panel.tailwind.css`, `panel.bootstrap5.css` (the `@source` of the theme's templates and the stylesheets of the
  third-party libraries) and `@byfareska/crud/panel.js` / `panel.bootstrap5.js` (the controllers in a Stimulus
  application of their own; for Bootstrap also Modal, Offcanvas and Dropdown).
- `byfareska_crud.icon_template`: the Twig template every icon is drawn with (`name`, `class`, `attributes`), for Font
  Awesome, an SVG sprite, Symfony UX Icons or images, without a theme of your own.
- Default texts made from the names (`CrudDefaultTexts`) for keys nobody translated: `crud.product.title` "Products",
  `.new` "New product", `.field.createdAt.name` "Created at", a menu section `app.nav.catalog` "Catalog", layout
  groups, actions and bulk actions. They load before the translation files of the bundles and the application, so
  those always win. `ResourceFieldNames` services add the fields a driver knows (`DoctrineFieldNames` for Doctrine).
- `DoctrineRecordLabels`: what names a related entity, in this order: a `RelationChoiceLabel`, the title of the
  resource of the entity, `__toString()`, its id. Used by forms, filters, the autocomplete, lists and details.
- With `symfony/ux-autocomplete` installed, the relations of guessed forms are searched as you type too, not only the
  relation filters. `RelationAutocompleter` answers them for who may add or edit records, within the scopes of the
  related resource. Aliasing `RelationFormField` to `ChoiceRelationFormField` brings back the plain select.
- Documentation in `docs/` (assets, configuration, resources, Doctrine, DTO, panels, dashboards, files, texts, command
  palette, extending, troubleshooting); the README is an overview that links to it.

### Changed

- A panel named only by resources has a dashboard at "/", as a configured one has. A panel with `dashboard: false`
  redirects "/" to its first list (route `crud_home_<panel>`; 404 when it has no list).
- Guessed Doctrine forms create an entity whose constructor takes arguments: fields named like the parameters supply
  them (`empty_data`). A required parameter that no field supplies is reported when the form is built.
- Guessed Doctrine forms leave out fields that cannot be written (no setter, not public, like a `createdAt` set in the
  constructor), except the constructor parameters of a new entity.
- A relation to the same entity (a parent category) does not offer the record being edited, in a select or in the
  autocomplete.
- No deprecations of Doctrine ORM 3.7: `LockMode::NONE` for `find()`, and the `SortDirection` enum for `ORDER BY`
  when the ORM accepts it (`DoctrineOrder`).

### Fixed

- Every page answered 500 ("Unknown function") when Symfony Reprise, the old default, was not installed. An asset tool
  that is configured but not installed now fails with a message naming its package.
- The menu failed on `path(null)` in the daisyui and tailwind themes when a panel had no home.
- The `doctrine` dashboard storage without its table: the dashboard shows the default layout and logs a warning, and
  saving answers 503 asking to run the migration, instead of a 500 from the database.
- Relations to entities without `__toString()` failed in forms and filters, and lists showed their ids.
- Untranslated texts were shown as their keys.

### Security

- The relation searches of `symfony/ux-autocomplete` went through the global `/autocomplete/{alias}`, which the
  panel's firewall and `access_control` do not cover. A panel protected by `access_control` alone (without `role`)
  revealed the names and ids of related entities to anybody who had the signed address of one of its fields. They now
  go through the panel's own address (see Upgrading).

## v0.1.0

Initial release.

- Requires `symfony/form` and `symfony/twig-bridge` 8.1 or later: the form theme of the daisyui theme extends
  `daisyui_5_layout.html.twig`, which Symfony has since 8.1.
- "Save" of the add and edit forms stays on the record: it leads to the edit form (of a new record too, once it is
  added), on the tab that was in view. `byfareska_crud.form.save: return` (or `AsCrud(save: AfterSave::Return)` for a
  resource) leads back to the list as before.
- `byfareska_crud.form.save` / `AsCrud::$save`: where "save" leads - `edit`, `return`, `show` (new: the details of the
  record), `new`, or the name of a `CrudSaveButton`.
- `byfareska_crud.form.save_buttons` / `AsCrud::$afterSave`: the buttons besides "save" for every resource; a resource
  that does not say (`afterSave` is now null by default) has those. `AfterSave::Return` ("save and back to the list")
  and `AfterSave::Show` ("save and view") are buttons too.
- `RecordSaving::$then` and `RecordSaved::$then` of "save" are where it leads (`edit`, `return`...), no longer null.
- Files, off until `byfareska_crud.files` is enabled (README: Files): storages of Flysystem or of `FileStorage` with
  references kept as `storage://path`; random sharded paths with the extension read from the bytes; public, temporary
  and signed addresses (sent by the web server with `offload`); libraries per storage (`doctrine`, `storage`, `none`,
  your own) and the window of the library; `CrudFileType` and `#[CrudFile]` - a file, many, a gallery - held in a
  column, items of a gallery (`binding: items`), entities of files or a `FileBinding`; the index of usages with
  `byfareska:crud:files:prune` and `byfareska:crud:files:reindex`; contract tests for adapters
  (`Files\Testing\*ContractTestCase`).
- Rich text: `CrudRichTextType` and `#[CrudRichText]`, an editor of the browser registered by name
  (`registerRichTextEditor()`), the adapter of TinyMCE (`@byfareska/crud/editors/tinymce.js`), images and files
  uploaded into the text with `files` (`byfareska_crud.rich_text`); TinyMCE speaks the language of the page
  (`<html lang>`) when its pack is loaded.
- A file taken into a field (uploaded or picked) gets its name without the extension, made plain by Symfony's slugger,
  as its alternative text until one is typed (`alt` of the JSON of a file).
- `uploadFile()` and `pickFiles()` of `@byfareska/crud`: an upload to the address of a field, the window of the library.
- Sizes of images, off until `byfareska_crud.files.images` is enabled (README: Sizes of images): named sizes
  (`thumbnail` and `preview` of the bundle, more of the application; `inside` or `cover`, a format, a quality) made by
  intervention/image 4 (`resizer: auto` - Imagick when PHP has it, keeping the colour profile, else GD) or an
  `ImageResizer` of your own (`Files\Testing\ImageResizerContractTestCase`); an image no browser shows (HEIC, TIFF)
  has its sizes in JPEG;
  kept in the storage of the image under `_variants/<size>/<path>`, made on upload or when first asked for
  (`storages.<name>.variants: eager | lazy | none`), deleted with the image;
  `byfareska:crud:files:variants:generate` and `byfareska:crud:files:variants:clear`. The lists show thumbnails, the
  details, the field and the window of the library `preview` - the image itself without sizes. `crud_file_url(ref,
  size)` in Twig. A field of rich text offers the sizes of an image in the editor (`files: {sizes, size}`), and the
  address of a size is read back as the image for the index of usages.
- The JSON of a file has `preview` and `sizes`; the editor of rich text gets `addressOf(file)` and `sizes`
  (`lib/rich_text.js`).
