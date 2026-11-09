# Troubleshooting

Clear the cache first: `bin/console cache:clear`. Routes and resource definitions are cached.

## `Unknown "reprise_entry_link_tags" function` (or `encore_entry_…`, `importmap`)

`byfareska_crud.assets` names a tool that isn't installed. Use `assets: auto` (the default), or set the tool you
actually use. See [assets.md](assets.md).

## `/bundles/crud/panel/panel.css` returns 404

The built-in assets aren't installed in `public/`. Run `bin/console assets:install`. Flex runs it after every
`composer install`, unless `auto-scripts` were removed from `composer.json`.

## The first page in development is slow, or some `/assets/…` requests fail with 502

Without entries of your own, the panel runs its script through AssetMapper, which in development serves each module
by its own request. After `cache:clear` the first page compiles them all, and Symfony CLI's local server can answer a
few with 502 while it does. Reload once it has warmed up. In production, `asset-map:compile` writes the files and
nothing is compiled per request.

## The panel has no styles, and menus or dialogs don't open

The pages load your entries, but they don't build the theme's stylesheet or register the bundle's controllers. Either
remove your own `entries` so that `auto` uses the built-in assets, or follow [assets.md](assets.md) for your tool:

- `import '@byfareska/crud/panel.js'` (or `panel.bootstrap5.js`) in your script;
- `@import '@byfareska/crud/styles/panel.<theme>.css'` in your stylesheet.

In the browser console, `Stimulus` warnings name the controllers that aren't registered.

## `/` shows the Symfony welcome page

The route import of the panel is missing, or the panel has `dashboard: false` and no resource has a `section`. Run
`bin/console debug:router | grep crud_` to see what was imported.

## `no such table: crud_dashboard_layout`

Dashboard layouts are stored with Doctrine by default. Run `bin/console doctrine:migrations:diff` and `migrate`, or set
`dashboard.storage: cache`.

## A list shows `crud.product.title` or `crud.product.field.name.name`

You're on a version without text fallbacks, or a translation exists but is empty. Add the keys from
[texts.md](texts.md).

## `Object of class … could not be converted to string`

A relation is shown in a form or a filter and the bundle can't name the related entity. Name it in one of these ways:

- declare a resource for the related entity (its `title:` or first column is used);
- add a `RelationChoiceLabel` service;
- implement `__toString()`.

## `The add form of "…" is guessed, and … has a constructor with arguments`

A constructor parameter has no form field with the same name, for example a value that comes from the signed-in user
or a service. Choose one:

- give the form that field: `fields:`;
- create the entity yourself: `new DoctrineForm(fields: [...], options: ['empty_data' => fn (FormInterface $form) => new Order(...)])`;
- alias `DoctrineEntityFactory`.

## `Could not determine access type for property "…"`

The form has a field the entity can't write. Leave it out (`fields:` of a `DoctrineForm`) or give it a setter.
Guessed forms leave out read-only properties already.

## SQLite deletes records that are still in use

SQLite enforces foreign keys only after `PRAGMA foreign_keys = ON`, on every connection. Without it, deleting a
category with products succeeds and leaves orphaned rows, instead of the bundle's "in use" error. Turn it on with
DBAL's middleware:

```yaml
# config/services.yaml
services:
    Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys:
        tags: [doctrine.middleware]
```

## A resource is missing from the menu or the palette

- It has no `section`.
- Its path has parameters (`/categories/{category}/products`).
- Its `role`, or its panel's `role`, isn't granted.

Without SecurityBundle, nobody has any role, so a resource with a `role` is visible to nobody.

## Fields of my own with `symfony/ux-autocomplete`

The bundle's relation fields search through the panel's address and check access. Fields of your own built with UX
Autocomplete (`#[AsEntityAutocompleteField]`, `BaseEntityAutocompleteType`) use the global `/autocomplete/{alias}`.
Their `security` option is `false` by default, so anybody can search them, signed in or not. Give them
`'security' => 'ROLE_…'`, or cover `^/autocomplete` in `access_control`.

## A component of mine named like the bundle's never renders

The bundle's components (`Ui:*`, `Crud:*`, `Panel:*`) are resolved first. The container refuses to build and names the
template: rename yours, or put it into a theme of your own (see [panels.md](panels.md#themes)).
