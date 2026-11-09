# Dashboards

`/` of every panel is a dashboard: a grid of widgets that each user moves, resizes, adds and removes (gridstack). Set
`dashboard: false` on a panel to skip it.

## Boards

A panel can have more dashboards, each with its own path, layouts and widgets. They appear in the menu after the
first one:

```yaml
byfareska_crud:
    scopes:
        admin:
            boards:
                warehouse: { label: app.board.warehouse, icon: box-seam }   # /admin/warehouse; "path:" to change it
```

- `role:` restricts a board.
- `template:` gives it a page of its own; it may extend `@CrudTheme/panel/dashboard.html.twig`.

## Widgets

A widget is a Twig (or live) component with `#[AsDashboardWidget]`:

```php
#[AsDashboardWidget(
    key: 'app.catalog',
    title: 'app.widget.catalog.title', description: 'app.widget.catalog.description',
    icon: 'box-seam', size: new WidgetSize(3, 2),
    role: 'ROLE_ADMIN', default: true, boards: ['admin'], lazy: false,
)]
#[AsTwigComponent('App:CatalogWidget', template: 'components/App/CatalogWidget.html.twig')]
final class CatalogWidget
{
    use DashboardWidgetProps; // placement, board, settings
}
```

| Argument | |
| --- | --- |
| `default: true` | on the layout everybody starts with |
| `boards` | the dashboards it may be on |
| `size` | cells of the grid (`dashboard.columns`, 12 by default) |
| `settings` | a settings dialog (see below) |
| `lazy` | loaded after the page |

`dashboard.disabled_widgets` removes widgets by key, the bundle's own included (`panel.clock`, `panel.note`,
`panel.quick_actions`). A `DashboardWidgetSource` yields widgets known only at run time (one per record, say). A
`DashboardWidgetFilter` hides widgets for a request (per tenant, behind a feature flag).

### Settings

```php
settings: [new WidgetSetting('zone', WidgetSettingType::Choice, choicesFrom: 'panel.timezones')],
```

| Setting type | Value |
| --- | --- |
| choice, choices | from `choices:`, or from a `WidgetChoices` service tagged `#[AsTaggedItem('…')]` |
| text | `maxLength:` |
| `Number` | `min:`, `max:`, `step:` |
| `Toggle` | `"1"` or `""` |
| `Date` | `Y-m-d` |
| `Color` | `#rrggbb` |

Whatever the browser sends, the widget receives a value of the right type or the default.

- `validator: FeedUrl::class` checks a value further with a `WidgetSettingValidator` service.
- `BoardWidgetChoices` provides choices that depend on the board.
- `personal: true` keeps a setting out of shared layouts (the text of a note, for example).

### Context

`DashboardContext` is what the whole dashboard is looked at for: `?context[period]=q3`, set by a GET form in the
`dashboard_filters` block. Widgets that load after the page receive it too.

## Charts

`ChartFactory` builds Chart.js charts in the page's colours:

- `line()`, `bar()`, …;
- `custom($type, $labels, $datasets, $options)` for any other type;
- `named('sales')` names a chart so that a `ChartExtension` can find it.

`TimeSeries` / `DailySeries` count rows per hour, day, week, month, quarter or year:

- `bucket($column, $granularity)` gives the SQL of the period;
- `since()`, `periods()`, `fill()` and `labels()` build the series.

The SQL comes from a `DayBucket` for the database platform (PostgreSQL, MySQL/MariaDB and SQLite are built in).
Another database needs one `PlatformDayBucket` class. Dates are stored in `chart.stored_timezone` and shown in the
`ChartTimezone` zone.

JavaScript functions can't be written in JSON. Name them on the server and register them in the browser:

```php
'ticks' => ['callback' => 'formatter(currency)']
```

```js
registerChartFormatter('currency', () => (value) => ...);
```

## Layouts

| Storage | |
| --- | --- |
| `doctrine` (default with ORM) | the table `crud_dashboard_layout`; add it with `doctrine:migrations:diff`. Until the table exists, everybody sees the default layout and saving is refused with a message |
| `cache` | `cache.app` |
| a service id | your own `DashboardLayouts` |

- **`DashboardPolicy`** decides who may do what. The default, `RoleDashboardPolicy`, lets every signed-in user arrange
  their own layout and `share_role` store it for everybody. Alias it for team defaults (`fallbackOwners()`) or for
  dashboards nobody may rearrange (`mayEdit()`).
- **`DashboardKey`** keeps layouts apart: by panel and URL parameters (`admin?shop=5`) by default; alias it for
  tenants or teams.
- **A widget hidden for a while keeps its place.** When a layout is saved without a widget that a filter, role or flag
  hid, that widget's position and settings are kept.
- **Events:** `DashboardLayoutSaved` and `DashboardLayoutShared` are dispatched.

The grid accepts `data-dashboard-grid-*-value` attributes (`columns`, `cell-height`, `mobile-breakpoint`,
`save-delay`). A listener of `dashboard-grid:options` can change any other gridstack option.
