# Changelog

## Unreleased

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

## v0.1.0

Initial release.
