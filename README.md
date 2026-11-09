# byfareska/crud

[![CI](https://github.com/byfareska/crud/actions/workflows/ci.yml/badge.svg)](https://github.com/byfareska/crud/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/byfareska/crud)](https://packagist.org/packages/byfareska/crud)
[![PHP Version](https://img.shields.io/packagist/dependency-v/byfareska/crud/php)](https://packagist.org/packages/byfareska/crud)
[![License](https://img.shields.io/packagist/l/byfareska/crud)](LICENSE)

Admin panels for Symfony, generated from one PHP attribute.

Put `#[AsCrud]` on a Doctrine entity and you get a list (search, filters, sorting, paging, bulk actions), a details
page with a preview drawer, add and edit forms, and delete with confirmation. Everything is built with Twig and
[Symfony UX Live Components](https://symfony.com/bundles/ux-live-component/current/index.html), so every list, form
and widget re-renders on its own without a JavaScript framework.

- **Resources** from an attribute, read by a **driver**: `doctrine` (entities), `dto` (DTOs and use cases), or your
  own.
- **Panels**: route scopes such as `/` and `/admin`, each with its own menu, theme, roles and **dashboard** of widgets
  that users arrange themselves.
- **Themes**: daisyUI (default), Tailwind CSS and Bootstrap 5. Override one template, not the whole theme.
- **Command palette** (the search field of the top bar): pages, commands and records, with a search algorithm you can replace.
- **Files**: uploads, galleries, a media library, rich text and image sizes, on any Flysystem storage.

The example application, [byfareska/crud-example](https://github.com/byfareska/crud-example), shows all of it.

## Requirements

- PHP 8.4+
- Symfony 8.0+ (Forms 8.1+)
- Symfony UX Live Component and Twig Component 2.20+ or 3.x, UX Chart.js
- Doctrine ORM 3 for the `doctrine` driver and dashboard layouts in a table (not needed for the `dto` driver)

### Strongly recommended

The panel works without these, but each one fixes something you will notice early. Install them together with the
bundle:

```sh
composer require symfony/security-bundle symfony/ux-turbo symfony/ux-autocomplete
# and the intl extension of PHP (apt install php-intl, docker-php-ext-install intl, …)
```

| Package | Why | Without it |
| --- | --- | --- |
| `symfony/security-bundle` | the signed-in user and their roles: `role:` of resources, panels and widgets, each user's own dashboard layout, the user menu with sign-out | nobody is signed in and nobody has a role, so anything with a `role` is hidden from everybody. Dashboards can't be rearranged, because layouts belong to a user |
| `symfony/ux-turbo` | page visits without full reloads: moving between lists, forms and the dashboard keeps the scripts loaded and the toasts (`data-turbo-permanent`); the bundle marks its assets with `data-turbo-track` so a new build or a panel with other entries still loads the whole page | every link and redirect is a full page load, so Stimulus and the live components start again on every page |
| `symfony/ux-autocomplete` | relations you type into, in the forms and in the filters of the lists: only the matching entities are loaded, in the scopes of the related resource (`DoctrineScope`), only for users who may add or edit records (forms) or see the list (filters), named as in the lists. The searches go through the panel's own address, so its firewall and `access_control` cover them | a relation is a `<select>` of **every** related entity, which gets slow and unusable with thousands of rows |
| `ext-intl` | the bundle's texts are ICU messages (`messages+intl-icu`: plurals, dates and numbers in the user's locale), and the command palette matches without accents ("zolw" finds "żółw") | translating any text of the panel throws "please install the intl PHP extension or symfony/polyfill-intl-messageformatter". The polyfills work, but format in English only; the accent-insensitive palette needs `Normalizer` (intl or `symfony/polyfill-intl-normalizer`) |

Once `symfony/ux-autocomplete` is installed, you don't need to configure anything for it:

- Every relation of a guessed form and every relation filter becomes a field you type into.
- The built-in assets already have its Stimulus controller and Tom Select in the look of the daisyui theme.
- With assets of your own, its recipe registers the controller, and the panel's stylesheet of your theme
  (`panel.daisyui.css`…) has Tom Select in the theme's look ([Assets](docs/assets.md#vite-symfony-reprise)).

To keep plain choices, alias `RelationFormField` to `ChoiceRelationFormField`
([Doctrine resources](docs/doctrine.md#relations)).

**Once you build your own assets, use [Symfony Reprise](docs/assets.md#vite-symfony-reprise).** You don't need a build
to start: the panel's assets come prebuilt, or through your AssetMapper. Once your templates or widgets add Tailwind
classes of their own, or you change a theme, the stylesheet has to be built with them. With Symfony Reprise (Vite)
that is one import in your stylesheet and one in your script, and it is what the bundle is tested with. Webpack Encore
and AssetMapper also work, with more setup ([Assets](docs/assets.md)).

## Quick start

```sh
composer require byfareska/crud
```

Symfony Flex registers the bundle. Import the routes of a panel:

```yaml
# config/routes/byfareska_crud.yaml
crud_panel:
    resource: panel # the name of the panel (its "scope")
    type: byfareska_crud
```

Put the attribute on an entity:

```php
use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Driver\Doctrine\DoctrineSource;

#[ORM\Entity]
#[AsCrud(
    name: 'product',
    path: '/products',
    icon: 'box-seam',                         // a Bootstrap Icons name, or any set: icon_template
    source: new DoctrineSource(Product::class),
    columns: ['name', 'category', 'createdAt'],
    section: 'Catalog',                       // the menu group: a translation key or plain text
)]
class Product
{
    public function __construct(string $name, Category $category) { /* ... */ }
    // ...
}
```

Open `/products`. You don't need a form type, a controller, a template or a `__toString()`:

- **Forms** are guessed from the Doctrine mapping. Constructor arguments are filled from fields of the same name, and
  properties without a setter (`createdAt`) are shown in the list but left out of the edit form.
- **A relation** is shown by the title of its own resource (its `title:` or first column), else by `__toString()`,
  else by its id.
- **Texts** fall back to readable names until you translate them ("Products", "Created at").
- **Assets**: nothing to set up.
  - With AssetMapper (a `--webapp` project), the panel runs its script through your importmap, with the Symfony UX
    controllers you have.
  - Without it, everything comes prebuilt.
  - Once your templates need styles of their own, name entries and use Symfony Reprise (recommended), Encore or
    AssetMapper. It is one import in your stylesheet and one in your script (see [Assets](docs/assets.md)).

When you need more, the [configuration reference](docs/configuration.md) lists every option. The usual next steps:
a theme built with your own Tailwind or Bootstrap ([Assets](docs/assets.md)), roles ([Panels](docs/panels.md)) and
the [Texts](docs/texts.md) of your resources.

## Documentation

| | |
| --- | --- |
| [Configuration](docs/configuration.md) | every option of `byfareska_crud` with its default |
| [Assets](docs/assets.md) | the built-in assets, AssetMapper, Vite (Symfony Reprise), Webpack Encore |
| [Doctrine resources](docs/doctrine.md) | `DoctrineSource`: forms, filters, search, sorting, scopes, ids, errors of the database |
| [DTO resources](docs/dto.md) | resources of DTOs and use cases; a driver of your own |
| [Resources in depth](docs/resources.md) | columns and value formats, layouts, actions, bulk actions, pages of your own, access, events |
| [Panels and themes](docs/panels.md) | several panels, the menu, the signed-in user, themes and their templates |
| [Dashboards](docs/dashboards.md) | widgets, boards, layouts and who may change them |
| [Command palette](docs/command-palette.md) | its sources and a search of your own |
| [Files](docs/files.md) | uploads, the library, rich text, image sizes |
| [Texts](docs/texts.md) | translation keys and the fallbacks |
| [Extending](docs/extending.md) | services to replace or decorate, resources declared by code |
| [Troubleshooting](docs/troubleshooting.md) | common errors and what they mean |

## Tests

```sh
composer install && vendor/bin/phpunit
make test
make lint        # deptrac, phpcs, PHPStan (level 10), Rector (dry run), composer validate, composer audit
make lint fix=1  # Rector and phpcbf fix what they can first
```

`deptrac.yaml` keeps the optional dependencies apart. Doctrine is used only by its integration (`Driver/Doctrine`,
`Chart/Sql`, the Doctrine dashboard storage), and Symfony Security only by `SecurityActor` and its `UserIdMapper`.
The rest of the bundle must work without either.

## License

MIT, see [LICENSE](LICENSE).
