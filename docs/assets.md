# Assets

The panel needs a stylesheet for its theme and its Stimulus controllers: dialogs, drawers, menus, toasts, the command
palette, the dashboard grid and date pickers. You don't need to configure anything: the default,
`byfareska_crud.assets: auto`, uses what your application has.

| Your application | What `auto` does |
| --- | --- |
| names no `entries` (the default), has AssetMapper + StimulusBundle (`symfony new --webapp`) | the prebuilt stylesheet, and the panel's script through **your** importmap: the Symfony UX controllers you have (live, autocomplete, Turbo…) in your versions, plus the bundle's. Your `app.js` and its styles are not loaded |
| names no `entries`, has neither | everything prebuilt (`builtin`) |
| names `entries` of its own | loads them with the first tool installed: **Symfony Reprise**, then **Webpack Encore**, then **AssetMapper**. Your entries then bring the panel's stylesheet and script (below) |

Reprise and Encore build only the entries your `vite.config.js` / `webpack.config.js` name, which the bundle can't add
to. Until you name entries, an application with them gets the prebuilt assets.

You can also choose the tool explicitly:

| `assets:` | What the pages load |
| --- | --- |
| `auto` (default) | as above |
| `builtin` | the prebuilt `/bundles/crud/panel/panel.css` and `panel.js` (put into `public/` by `assets:install`, which Flex runs on every `composer install`) |
| `importmap` | `{{ importmap(entries) }}`, from AssetMapper |
| `reprise` | `reprise_entry_link_tags` / `reprise_entry_script_tags` of each entry (Vite or Rsbuild through Symfony Reprise) |
| `encore` | `encore_entry_link_tags` / `encore_entry_script_tags` of each entry |
| `none` | nothing: a layout of your own loads them |

Only the Twig functions of the selected tool are used, so the others don't need to be installed. If you select a
tool that isn't installed, the bundle stops with an error naming the package to install.

**Which to choose:**

- Nothing to change in the panel's look → leave it as it is.
- Your own templates or widgets with Tailwind classes, or a theme of your own → your own entries with **Symfony
  Reprise** (recommended).
- No Node.js at all → AssetMapper with your own entries works for the scripts. Tailwind classes of your own need the
  standalone Tailwind CLI (`symfonycasts/tailwind-bundle`).

## Third-party code

The panel's own assets import gridstack, vanilla-calendar-pro and the stylesheets of Bootstrap Icons, gridstack,
vanilla-calendar-pro and Tom Select from the bundle's copies in `assets/vendor` (with their licences), by relative
paths. So none of them is a package you install, and every tool resolves them: Vite, Encore, AssetMapper, the
standalone Tailwind CLI. The bundle's `build/panel` makes the copies.

What stays yours: `@hotwired/stimulus` and the Symfony UX packages (the controllers must share one copy of Live
Component with the rest of the page), Tailwind CSS and daisyUI, Bootstrap for the `bootstrap5` theme.

## Built-in assets

`public/panel` of the bundle holds two prebuilt files:

- `panel.css`: the `daisyui` and `tailwind` themes (every class their templates use), every daisyUI colour theme, and
  the third-party stylesheets above. It is the panel's stylesheet whenever your application names no entries.
- `panel.js`: a Stimulus application with the bundle's controllers, the `live` controller of Symfony UX Live
  Component, the chart controller of UX Chart.js and the autocomplete controller of UX Autocomplete. It is the
  panel's script only when your application has no AssetMapper (`builtin`). gridstack and the calendar are chunks
  loaded only on the pages that use them.

`assets:install` copies them to `public/bundles/crud/panel`, which is where the pages load them from, with `?v=` and a
hash of the build in the address. They are linked directly, not through AssetMapper. If
`/bundles/crud/panel/panel.css` returns 404, run `bin/console assets:install`.

Limits of the prebuilt files:

- You can't add classes of your own: Tailwind only built the classes it found in the bundle's templates.
- The `bootstrap5` theme has no prebuilt stylesheet: Bootstrap is the application's.
- `panel.js` has the controllers of Symfony UX built from the versions the bundle pins (`build/panel/package.json`).
  With AssetMapper that doesn't matter, since your own versions are used. Without it, an application that requires
  another major version of Live Component should build its own assets.

To rebuild after changing the templates, the controllers or the copies:

```sh
cd build/panel             # in the bundle's repository
npm ci && npm run build    # writes assets/vendor and public/panel
```

## AssetMapper

There's nothing to configure. The bundle maps its `assets/` directory as `@byfareska/crud` and adds two entries to
your importmap, in memory only. It never writes them to `importmap.php`, and `importmap:require` and friends keep the
file as it was:

| Entry | |
| --- | --- |
| `@byfareska/crud/panel.importmap.js` | an entrypoint: the script the panel loads by itself while you name no entries |
| `@byfareska/crud` | the bundle's controllers by name, for entries of your own |

An entry of the same name in your `importmap.php` (from an older setup) wins, and you can remove it.

With entries of your own, register the controllers in your Stimulus application. One application, so the UX
controllers aren't registered twice:

```yaml
byfareska_crud:
    entries: [app]
```

```js
// assets/stimulus_bootstrap.js
import { startStimulusApp } from '@symfony/stimulus-bundle';
import { registerControllers } from '@byfareska/crud';

registerControllers(startStimulusApp());
```

The stylesheet is then yours too. For `bootstrap5` (Bootstrap from `importmap:require bootstrap`) import
`@byfareska/crud/styles/panel.bootstrap5.css` after Bootstrap. For the Tailwind themes, build your stylesheet with
`symfonycasts/tailwind-bundle` and import the theme's file by its path:

```css
/* assets/styles/app.css */
@import 'tailwindcss';
@import '../../vendor/byfareska/crud/assets/styles/panel.daisyui.css';
@plugin 'daisyui' { themes: all; }   /* the standalone Tailwind CLI loads daisyUI from a file: see daisyUI's docs */
```

The bundle hasn't been tested with `symfonycasts/tailwind-bundle` yet.

In development, AssetMapper serves every module by its own request. The first page after `cache:clear` can be slow,
and Symfony CLI's server can answer a few of them with 502 until it has warmed up. Production (`asset-map:compile`)
serves files.

## Vite (Symfony Reprise)

This is what the bundle is developed and tested with: the Tailwind themes are built in the same pipeline as the
scripts. Everything the panel needs is one stylesheet and one script per theme. Webpack Encore should take the same
imports (Tailwind through `@tailwindcss/postcss`), but the bundle isn't tested with it.

```sh
npm install --save-dev file:vendor/byfareska/crud/assets
npm install --save-dev tailwindcss @tailwindcss/vite daisyui   # daisyui theme
npm install --save-dev tailwindcss @tailwindcss/vite           # tailwind theme
npm install --save-dev bootstrap                                # bootstrap5 theme
```

**daisyui** (Tailwind CSS 4 + daisyUI 5). You only choose the colour themes:

```css
/* assets/app.css */
@import 'tailwindcss';
@import '@byfareska/crud/styles/panel.daisyui.css';
@plugin 'daisyui' { themes: all; }   /* or the ones of byfareska_crud.color_themes: light --default, dark --prefersdark, nord */
@source '../templates';
```

```js
// assets/app.js
import '@byfareska/crud/panel.js';
import './app.css';
```

The top bar has a colour theme picker. The choice is `data-theme` on `<html>`, kept in `localStorage` under
`panel-theme-daisyui` and restored before the first paint.

**tailwind** (Tailwind CSS 4):

```css
/* assets/app.css */
@import 'tailwindcss';
@import '@byfareska/crud/styles/panel.tailwind.css';
@source '../templates';
```

```js
// assets/app.js
import '@byfareska/crud/panel.js';
import './app.css';
```

**bootstrap5**: Bootstrap stays yours, either its dist stylesheet or your own Sass build of it:

```js
// assets/app.js
import '@byfareska/crud/panel.bootstrap5.js';           // the controllers + Bootstrap's modals, offcanvas, dropdowns
import 'bootstrap/dist/css/bootstrap.min.css';
import '@byfareska/crud/styles/panel.bootstrap5.css';
```

What the files hold:

| File | |
| --- | --- |
| `styles/panel.daisyui.css` | `@source` of the theme's templates, Bootstrap Icons, gridstack, the calendar of the date filters, Tom Select in the daisyui look |
| `styles/panel.tailwind.css` | the same for the tailwind theme, and its dark mode (`@custom-variant dark` on `data-theme="dark"`) |
| `styles/panel.bootstrap5.css` | Bootstrap Icons, gridstack, the calendar, Tom Select's Bootstrap 5 stylesheet |
| `panel.js` | the bundle's controllers, registered in a Stimulus application of their own, and the CSRF listener |
| `panel.bootstrap5.js` | `panel.js` plus Bootstrap's Modal, Offcanvas and Dropdown handed to the controllers (`useBootstrap()`) |

Some notes:

- **Symfony UX controllers.** `panel.js` runs next to your application's Stimulus, which keeps the controllers the
  Symfony UX recipes registered (`live`, autocomplete, chart). It doesn't register them again, so don't remove them
  from `assets/controllers.json`.
- **Tom Select stylesheet.** The UX Autocomplete recipe also imports `tom-select.default.css` (`autoimport` in
  `assets/controllers.json`). The panel files already have it, in the right order, so set that `autoimport` to
  `false`.
- **Linked bundle.** `@hotwired/stimulus` and the Symfony UX packages are resolved from your `node_modules`. If the
  bundle is linked (a composer `path` repository), set `resolve.preserveSymlinks: true` in `vite.config.js`.
- **More control.** To register the controllers into your own Stimulus application with options (`except`,
  `prefix`), import `registerControllers()` instead of `panel.js` (see below).

## Entries per theme and per panel

```yaml
byfareska_crud:
    entries: [panel_{theme}, app]       # panel_daisyui, app
    scopes:
        admin: { theme: tailwind, entries: [charts] }   # panel_tailwind, app, charts
```

Turbo tracks the entries, so moving between panels with different entries reloads the whole page.

## `registerControllers()` options

```js
registerControllers(app, { except: ['dashboard-grid'], prefix: 'crud--', csrf: false });
```

- `except`: controllers to leave out, for example to register your own under that name.
- `prefix`: added before every name. The bundle's templates use the names without a prefix.
- `csrf: false`: skips the CSRF listener. If you register controllers one by one, call `installCsrfProtection()`
  yourself.

gridstack and vanilla-calendar-pro are loaded only on pages with a dashboard or a date field.
