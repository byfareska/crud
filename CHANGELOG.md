# Changelog

## Unreleased

- "Save" of the add and edit forms stays on the record: it leads to the edit form (of a new record too, once it is
  added), on the tab that was in view. `byfareska_crud.form.save: return` (or `AsCrud(save: AfterSave::Return)` for a
  resource) leads back to the list as before.
- `byfareska_crud.form.save` / `AsCrud::$save`: where "save" leads - `edit`, `return`, `show` (new: the details of the
  record), `new`, or the name of a `CrudSaveButton`.
- `byfareska_crud.form.save_buttons` / `AsCrud::$afterSave`: the buttons besides "save" for every resource; a resource
  that does not say (`afterSave` is now null by default) has those. `AfterSave::Return` ("save and back to the list")
  and `AfterSave::Show` ("save and view") are buttons too.
- `RecordSaving::$then` and `RecordSaved::$then` of "save" are where it leads (`edit`, `return`...), no longer null.

## v0.1.0

Initial release.
