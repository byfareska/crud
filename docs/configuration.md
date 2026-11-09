# Configuration

You don't need any configuration: a route import and `#[AsCrud]` are enough. This page lists every option with its
default.

```yaml
# config/packages/byfareska_crud.yaml
byfareska_crud:
    name: ~                  # the brand in the menu and the page titles
    assets: auto             # auto | builtin | importmap | reprise | encore | none (see assets.md)
    entries: [app]           # your entries; naming them makes "auto" load them with Reprise, Encore or AssetMapper
                             # (in that order) instead of the panel's own assets. "{theme}" = the page's theme
    theme: daisyui           # daisyui | tailwind | bootstrap5 | one of "themes"; '%env(CRUD_THEME)%' works
    themes: {}               # the application's own themes (see panels.md)
    # color_themes: [light, dark, nord]   # the daisyUI colour themes offered in the top bar; default: all 35
    # dark_color_themes: [dark]           # which of them are dark (charts, native controls)
    layout: '@CrudTheme/panel/layout.html.twig'   # what the pages extend
    icon_template: '@CrudTheme/crud/icon.html.twig'  # the Twig template of every icon: another set (see panels.md#icons)
    paths: { new: new, edit: edit, page: page }   # /products/new, /products/7/edit, /products/page/3

    preview: true            # a click on a row opens the preview drawer; AsCrud "preview" per resource
    per_page: [15, 30, 50, 100]
    default_per_page: ~      # null = the first of per_page
    form:
        save: edit           # where "save" leads: edit | return | show | new | a CrudSaveButton
        save_buttons: []     # more buttons, e.g. [return] = "save and back to the list"

    scopes:                  # the panels; one is created for every scope a resource names
        panel: { icon: house-door }
        admin: { icon: shield-lock, role: ROLE_ADMIN, dashboard: true }
        # docs: { theme: tailwind, entries: [docs] }               # a theme and entries of its own
        # shop: { paths: { new: nowy, edit: edycja, page: strona } } # address words in its language

    value_themes: []         # templates with value blocks for every resource
    form_themes: []          # form themes after the panel theme's own (their blocks win)

    command_palette:
        search: Byfareska\Crud\CommandPalette\TextPaletteSearch
        suggested: []        # entries shown before anything is typed
        locales: []          # for byfareska:crud:command-palette:index; empty = framework.enabled_locales
        limit: 14
        records_per_source: 5

    dashboard:
        storage: ~           # doctrine | cache | a DashboardLayouts service id; default doctrine with ORM
        share_role: ROLE_ADMIN
        disabled_widgets: [] # e.g. [panel.note]; the bundle's own: panel.clock, panel.note, panel.quick_actions
        columns: 12

    chart:
        day_bucket: ~        # postgresql | mysql | sqlite | a service id; default from the Doctrine platform
        stored_timezone: UTC # the zone date columns are stored in; ~ for PostgreSQL timestamptz

    # files: ...             # off until configured, see files.md
    # rich_text: ...         # see files.md
```

## Panels (`scopes`)

| Key | Default | |
| --- | --- | --- |
| `icon` | `house-door` | the panel switch and the menu |
| `label` | `panel.scope.<name>` | a translation key |
| `role` | none | who may see the panel at all: its menu, palette, dashboard and every resource in it |
| `dashboard` | `true` | whether `/` of the panel is a dashboard of widgets; `false` makes `/` redirect to the first resource in the menu |
| `boards` | `{}` | more dashboards, see [dashboards.md](dashboards.md) |
| `home` | the dashboard | the route the panel switch and the brand lead to |
| `theme`, `entries`, `layout`, `paths` | the global ones | per panel |

A route import that names a panel with no configuration and no resources fails: a typo in the name would otherwise
create a panel without a role.

## Route import

```yaml
crud_admin:
    resource: admin                 # the panel
    type: byfareska_crud
    prefix: /admin                  # may have parameters: /{shop}/admin
    trailing_slash_on_root: false
```

Each import adds:

- `crud_dashboard_<panel>` at `/` (when the panel has a dashboard) and its JSON endpoints under `/_dashboard`;
- `crud_<resource>_list`, `_new`, `_show`, `_edit`, plus the routes of `#[AsCrudPage]`, for every resource of the
  panel;
- `crud_live_<panel>` at `/_crud/{component}/{action}`: the live components of the panel, under its prefix, so the
  firewall of `^/admin` covers them;
- `crud_autocomplete_<panel>` at `/_crud/autocomplete/{alias}`, with `symfony/ux-autocomplete`: the searches of the
  relations of its forms and filters, under its prefix for the same reason;
- the file endpoints under `/_files`, when [files](files.md) are enabled.

Every route has the default `_scope`. Run `bin/console debug:router | grep crud_` to see them.
