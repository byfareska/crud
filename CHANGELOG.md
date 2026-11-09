# Changelog

## Unreleased

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
