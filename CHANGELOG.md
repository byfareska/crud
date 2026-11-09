# Changelog

## Unreleased

- **The minimal setup works**: the bundle, one route import and `#[AsCrud]` on an entity make a working panel - no
  configuration, no form type, no `__toString()`, no build of the application's own.
- `byfareska_crud.assets` is `auto` by default (was `reprise`): the prebuilt assets of the panel while the application
  names no `entries`, else the tool that is installed (Symfony Reprise, AssetMapper, Webpack Encore). A tool named but
  not installed is told by its package, not by "Unknown function" of Twig.
- `byfareska_crud.assets: builtin`: the prebuilt stylesheet (the daisyui and tailwind themes, every daisyUI colour
  theme, Bootstrap Icons, gridstack, the calendar) and script (Stimulus, the controllers of the bundle, the live
  controller of Symfony UX Live Component 3.7, the chart controller of UX Chart.js) of `public/panel`, installed by
  `assets:install` as `bundles/crud/panel`. Built by `build/panel` (`npm ci && npm run build`).
- A panel that only resources name has a dashboard at "/" too, as a configured one has. One with `dashboard: false`
  leads "/" to its first list (`crud_home_<panel>`; 404 for a panel without a list); the brand of the menu no longer
  fails without a home.
- The `doctrine` dashboard storage without its table: everybody sees the default layout (a warning is logged) and a
  save answers 503 saying to migrate, instead of a 500 of the database.
- Guessed Doctrine forms make an entity whose constructor takes arguments: the fields named as its parameters give
  them (`empty_data`). A required parameter no field gives is told when the form is made.
- Guessed Doctrine forms leave out what cannot be written (no setter, not public - a `createdAt` set in the
  constructor), but for the parameters of the constructor of a new entity.
- What names a related entity (`DoctrineRecordLabels`): a `RelationChoiceLabel`, else the title of the resource of
  its entity, else `__toString()`, else its id - in the choices of forms and filters, the autocomplete, the lists and
  the details. A relation is a `TypedValue('relation', RecordRef)`: its text the name, its JSON the id. A relation
  to the same entity (a parent) does not offer the record being edited.
- Texts nobody translated are made of the names (`CrudDefaultTexts`, a catalogue before the files of the bundles and
  the application): `crud.product.title` "Products", `.new` "New product", `.field.createdAt.name` "Created at",
  a menu section `app.nav.catalog` "Catalog"; layout groups, actions and bulk actions too. Help, toasts and the
  keywords of the palette are not made. `ResourceFieldNames` services add fields a driver knows.
- With `symfony/ux-autocomplete` the relations of guessed forms are fields to search in too, not only the relation
  filters - nothing to configure (`RelationFormField`: `AutocompleteRelationFormField`, else
  `ChoiceRelationFormField`). `RelationAutocompleter` answers them for who may add or edit records, in the scopes of
  the related resource, never offering the record being edited. The built-in assets have the autocomplete controller
  and Tom Select in the look of the daisyui theme.
- The relation searches (`symfony/ux-autocomplete`) go through an address of each panel,
  `<prefix>/_crud/autocomplete/{alias}` (`crud_autocomplete_<panel>`), so the panel's firewall and `access_control`
  cover them. `RelationAutocompleter` refuses the global `/autocomplete/{alias}` and the address of another panel.
  Before, a panel protected by `access_control` alone (no `role`) told the names and ids of its related entities to
  anybody who had a signed address of one of its fields.
- `byfareska_crud.icon_template`: the Twig template every icon is drawn with (`name`, `class`, `attributes`) - Font
  Awesome, an SVG sprite, Symfony UX Icons, images of AssetMapper, names mapped - without a theme of your own. The
  toasts draw their icons with `crud_icon()` too (`<template data-toast-target="icon" data-variant="…">`, cloned by the
  toast controller), no longer the classes of Bootstrap Icons. A toast template of your own without these templates
  works as before.
- One import per theme for an application's own build: `@byfareska/crud/styles/panel.daisyui.css`,
  `panel.tailwind.css`, `panel.bootstrap5.css` (the `@source` of the theme's templates, Bootstrap Icons, gridstack,
  the calendar, Tom Select in the theme's look) and `@byfareska/crud/panel.js` / `panel.bootstrap5.js` (the
  controllers in a Stimulus application of their own; Bootstrap's components handed over). The built-in assets are
  built from these files.
- Nothing to configure for the assets in any application. `auto` without entries now runs the panel's script
  through the application's AssetMapper when it has one with StimulusBundle: `assets/panel.importmap.js`, an
  entrypoint the bundle adds to the importmap in memory (`PanelImportMapConfigReader`, never written to
  `importmap.php`). It uses the Symfony UX controllers and versions the application has, with the prebuilt
  stylesheet; without AssetMapper, everything prebuilt as before. `@byfareska/crud` is mapped the same way, so
  `registerControllers()` needs no `importmap.php` line. With entries the tool is Reprise, then Encore, then
  AssetMapper.
- The third-party code the panel's assets import - gridstack, vanilla-calendar-pro, the stylesheets of Bootstrap Icons,
  gridstack, vanilla-calendar-pro and Tom Select - is the bundle's own copy in `assets/vendor` (with licences, made
  by `build/panel`), imported by relative paths: no package to install for them (no longer peer dependencies), and
  every tool resolves them, the standalone Tailwind CLI and AssetMapper too.
- No deprecations of Doctrine ORM 3.7: `LockMode::NONE` for `find()`, the `SortDirection` enum for `ORDER BY` when the
  ORM takes it (`DoctrineOrder`).
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

## v0.1.0

Initial release.
