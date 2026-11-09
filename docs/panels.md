# Panels and themes

## Panels

A panel (a "scope") is a group of resources with its own route prefix, menu, dashboard, theme, role and command
palette. Every resource belongs to one panel (`scope:`, `panel` by default). Each panel is imported once:

```yaml
# config/routes/byfareska_crud.yaml
crud_panel:
    resource: panel
    type: byfareska_crud

crud_admin:
    resource: admin
    type: byfareska_crud
    prefix: /admin
    trailing_slash_on_root: false
```

```yaml
# config/packages/byfareska_crud.yaml
byfareska_crud:
    scopes:
        admin: { icon: shield-lock, role: ROLE_ADMIN }
```

`/` of a panel is its dashboard. With `dashboard: false` it redirects to the first resource in its menu. The brand
and the panel switch lead there.

The live components of a panel are served under its prefix (`/admin/_crud/...`), so a panel behind a firewall of its
own is complete: an admin panel on `^/admin` and a partner portal on `^/partner` can have different user classes.

### Parameters in the prefix

`prefix: /{shop}/admin` gives one panel per shop. The bundle passes the parameters on (`PageParameters`) to every
URL generated on the page and in live requests: row links, "back", the page after a save. Whether the signed-in
user may see that shop is for your application to check, with a `DoctrineScope`, a guard or a `ScopeExtension`.

A `ScopeExtension` changes a panel for the request, for example `$scope->with(brand: $shop->name)`, a theme per
tenant or a role behind a feature flag. It can't change the panel's name or whether it has a dashboard.

## The menu

The menu is made of:

- resources with a `section`;
- the dashboards;
- items added by `NavigationSource` services (`NavigationItem`: a route or `url:`, `target`, `badge`, `parent` for
  nesting, `attributes`).

A `NavigationExtension` changes the whole menu for the request: hide items, reorder them, add badges.

A component with `#[AsTopbarWidget(priority: 10)]` goes into the top bar. A `TopbarWidgetSource` adds widgets known
only at run time.

## The signed-in user

| Service | Default | Alias it to |
| --- | --- | --- |
| `Actor` | reads Symfony Security; without it nobody is signed in and no role is granted | a different source of roles |
| `UserIdMapper` | `getId()` of the user, else its identifier | `'customer:' . $user->getId()` when two user classes share ids |
| `PanelAccount` | name, identifier, the firewall's logout (with its CSRF token), the way back from `switch_user` | single sign-on logouts |
| `IdMapper` | ids as in JSON | ids of a kind of your own |

A `role` that nobody can check (no SecurityBundle) means nobody, not everybody.

## Themes

| Theme | |
| --- | --- |
| `daisyui` (default) | Tailwind CSS 4 + daisyUI 5. `<dialog class="modal">`, `<details>` menus, a colour theme picker |
| `tailwind` | Tailwind CSS 4. Native `<dialog>` and `<details>`, dark mode via `dark:` |
| `bootstrap5` | Bootstrap 5.3. Modal, offcanvas, dropdown; light and dark via `data-bs-theme` |

Templates are requested as `@CrudTheme/...` and resolved in the current theme, then its parent. A theme of your own is
a directory containing **only the templates it changes**:

```yaml
byfareska_crud:
    theme: acme
    themes:
        acme: { path: '%kernel.project_dir%/templates/crud_theme', parent: daisyui }
```

```twig
{# templates/crud_theme/components/Crud/List.html.twig #}
{% extends '@CrudTheme.daisyui/components/Crud/List.html.twig' %}
{% block list_footer %}{{ parent() }}<p class="text-sm">…</p>{% endblock %}
```

`@CrudTheme.<name>/...` always refers to that theme. `crud_theme()` tells a template which theme is in use, and
`Themes::use()` switches it for the rest of the request. To choose the theme differently (per user, say), alias
`ThemeSelector`.

| Template | Blocks |
| --- | --- |
| `components/Crud/List.html.twig` | `list_toolbar`, `list_filters`, `list_above_rows`, `list_bulk_bar`, `list_table` (`list_columns`, `list_row`, `list_cell`, `list_row_actions`, `list_empty`), `list_footer`, `list_preview` |
| `components/Crud/Form.html.twig` | `form_head`, `form_actions`, `form_fillers`, `form_fields` |
| `components/Panel/Sidebar.html.twig` | `sidebar_brand`, `sidebar_sections`, `sidebar_footer` |
| `panel/dashboard.html.twig` | `dashboard_head`, `dashboard_actions`, `dashboard_grid` |
| `panel/layout.html.twig` | `page_title`, `content`, `main_class`, `topbar_start`, `topbar_actions`, `user_menu_items` |

The bundle's components (`Ui:*`, `Crud:*`, `Panel:*`) are resolved before the application's templates, so a component
of yours with the same name (`templates/components/Ui/Modal.html.twig`) would never render. The container refuses
to build and names it.

A layout of your own extends `@CrudTheme/panel/base.html.twig`. It must include `<twig:Panel:CommandPaletteModal />`,
`<twig:Ui:Toast id="panel-toast" data-turbo-permanent />` and `<twig:Ui:ConfirmDialog />`, plus the session toasts
(`app.flashes('toast')`).

Templates that use the `Ui:*` components (`Ui:Widget`, `Ui:PageHead`…) look right in every theme.

## Icons

Every icon of the panel is drawn by `crud_icon(name, class, attributes)`, toasts included. By default that is
Bootstrap Icons by name (`<i class="bi bi-box-seam">`). To use another set, point `icon_template` at a Twig template
of yours:

```yaml
byfareska_crud:
    icon_template: crud/icon.html.twig
```

It gets `name` (as written in `icon:` arguments and used by the bundle), `class` (sizes, colours) and `attributes`
(print them with `crud_attrs(attributes)`). Because it is Twig, it can map names, use `asset()` and AssetMapper,
`ux_icon()` of Symfony UX Icons, an SVG sprite, or anything else:

```twig
{# templates/crud/icon.html.twig - Font Awesome #}
{% set map = {'three-dots': 'ellipsis', 'x-lg': 'xmark', 'trash': 'trash-can', 'box-seam': 'box'} %}
<i class="fa-solid fa-{{ map[name] ?? name }}{{ class ? ' ' ~ class }}"{{ crud_attrs(attributes) }} aria-hidden="true"></i>
```

```twig
{# templates/crud/icon.html.twig - images of AssetMapper #}
<img src="{{ asset('icons/' ~ name ~ '.svg') }}" class="size-4{{ class ? ' ' ~ class }}" alt=""{{ crud_attrs(attributes) }} aria-hidden="true">
```

```twig
{# templates/crud/icon.html.twig - Symfony UX Icons #}
{{ ux_icon('bi:' ~ name, {class: 'size-4 ' ~ class}|merge(attributes)) }}
```

Names the bundle uses itself, to map when your set calls them differently: `arrow-counterclockwise`, `arrow-left`,
`arrow-return-left`, `box-arrow-right`, `calendar3`, `check-circle`, `check-lg`, `check2`, `chevron-down`,
`chevron-left`, `chevron-right`, `circle-half`, `clock`, `collection`, `copy`, `exclamation-triangle`, `eye`,
`file-earmark`, `file-earmark-text`, `funnel`, `gear`, `graph-up`, `grid-1x2`, `image`, `info-circle`,
`lightning-charge`, `list`, `magic`, `palette`, `pencil`, `people`, `plus-lg`, `search`, `sticky`, `three-dots`,
`trash`, `upload`, `window`, `x-circle`, `x-lg`. Panels, boards and resources use the names their `icon` gives.

A theme of your own can still replace `crud/icon.html.twig` for that theme alone; `icon_template` wins over it.

The built-in assets keep Bootstrap Icons' stylesheet. The browser downloads its font only for a page that shows one
of its icons, so a page drawn entirely with another set doesn't load it.

Form themes: the panel theme's own form theme comes before the application's `twig.form_themes`, and
`byfareska_crud.form_themes` come after it, so their blocks take precedence.
