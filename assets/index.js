// Not a controller: it listens on document and fills the stateless CSRF token of every form that has the field.
import { installCsrfProtection } from './controllers/csrf_protection_controller.js';
import ChartTheme from './controllers/chart_theme_controller.js';
import Clock from './controllers/clock_controller.js';
import CommandPalette from './controllers/command_palette_controller.js';
import Comparison from './controllers/comparison_controller.js';
import Confirm from './controllers/confirm_controller.js';
import DashboardGrid from './controllers/dashboard_grid_controller.js';
import DateRange from './controllers/date_range_controller.js';
import Dropdown from './controllers/dropdown_controller.js';
import FileField from './controllers/file_field_controller.js';
import FileLibrary from './controllers/file_library_controller.js';
import FiltersPanel from './controllers/filters_panel_controller.js';
import Flash from './controllers/flash_controller.js';
import FocusFirst from './controllers/focus_first_controller.js';
import Indeterminate from './controllers/indeterminate_controller.js';
import LiveSet from './controllers/live_set_controller.js';
import Overlay from './controllers/overlay_controller.js';
import ScrollTo from './controllers/scroll_to_controller.js';
import ShowIf from './controllers/show_if_controller.js';
import Tabs from './controllers/tabs_controller.js';
import Theme from './controllers/theme_controller.js';
import Toast from './controllers/toast_controller.js';

// The dialogs of the bootstrap5 theme: useBootstrap({ Modal, Offcanvas, Dropdown }) - see lib/overlay.js.
export { useBootstrap, openOverlay, closeOverlay, toggleOverlay, closeMenus, anyOverlayOpen, registerOverlayAdapter } from './lib/overlay.js';
// For an application that registers single controllers of the map below instead of calling registerControllers().
export { installCsrfProtection };
// Files of the panel: an upload to the address of a field, the window of the library - see lib/files.js.
export { uploadFile, pickFiles } from './lib/files.js';
// Functions of charts by name: "formatter(currency)" in the description of a chart - see lib/chart_formatters.js.
export { registerChartFormatter } from './lib/chart_formatters.js';

/*
 * The Stimulus controllers of byfareska/crud, under the names its templates use (data-controller="…").
 * The application registers them next to its own: registerControllers(app), or one by one from this map.
 */
export const controllers = {
    'chart-theme': ChartTheme,
    'clock': Clock,
    'command-palette': CommandPalette,
    'comparison': Comparison,
    'confirm': Confirm,
    'dashboard-grid': DashboardGrid,
    'date-range': DateRange,
    'dropdown': Dropdown,
    'file-field': FileField,
    'file-library': FileLibrary,
    'filters-panel': FiltersPanel,
    'flash': Flash,
    'focus-first': FocusFirst,
    'indeterminate': Indeterminate,
    'live-set': LiveSet,
    'overlay': Overlay,
    'scroll-to': ScrollTo,
    'show-if': ShowIf,
    'tabs': Tabs,
    'theme': Theme,
    'toast': Toast,
};

/*
 * registerControllers(app) registers every controller and installs the CSRF listener. The options:
 *
 * - except: identifiers left out (['dashboard-grid', 'date-range']) - e.g. to register a controller of the
 *   application's own under that name;
 * - prefix: put before every identifier ('crud--' gives data-controller="crud--tabs"); the templates of the bundle use
 *   the names without one, so a prefix is for an application that draws its own markup;
 * - csrf: false - the CSRF listener is not installed (the application has its own).
 */
export function registerControllers(app, options = {}) {
    const { except = [], prefix = '', csrf = true } = options;

    if (csrf) {
        installCsrfProtection();
    }
    for (const [name, controller] of Object.entries(controllers)) {
        if (!except.includes(name)) {
            app.register(prefix + name, controller);
        }
    }
}
