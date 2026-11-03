import { Controller } from '@hotwired/stimulus';
import { chartFormatter } from '../lib/chart_formatters.js';

/*
 * The colours of a chart of symfony/ux-chartjs, in the colours of the page. The chart is described on the server with
 * CSS custom properties instead of colours (ChartFactory):
 *
 *   "var(--violet)": the property where the canvas is - else that colour of PALETTE ("var(--bs-primary)" too)
 *   "gradient(var(--violet))": the area under a line: the colour fading to nothing downwards
 *
 * The controller sits on the canvas next to the chart's own and is connected first, so it changes the description
 * before the chart is drawn (chartjs:pre-connect) and again when a live component sends a new one
 * (chartjs:view-value-change). The text and the axes take the colour of the text around the chart, unless the
 * description has its own (options.color, options.font - merged, the server's first); the tooltip the custom
 * properties --chart-tooltip-bg, -title, -body and -border where the canvas is. When the colour scheme changes
 * (data-bs-theme / data-theme on <html>), every chart is drawn again.
 *
 * A function the server cannot write - a tick in "1 234,50 zł" - is named instead: "formatter(currency)", of
 * registerChartFormatter() (lib/chart_formatters.js). Anything else of the application is set by a listener of
 * "chart-theme:options" on the canvas, given event.detail.config each time the chart is made or drawn again (in another
 * theme, with new data): what it puts there - a function too - is kept.
 *
 * A new description (a widget polling) goes into the datasets the chart has: Chart.js knows a dataset by the object,
 * so the new objects ux-chartjs would hand it are drawn as new charts, from the axis up, not from what was shown.
 *
 * <canvas data-controller="chart-theme symfony--ux-chartjs--chart" data-action="chartjs:pre-connect->chart-theme#preConnect …">
 */
const charts = new Map();
let observer = null;

export default class extends Controller {
    connect() {
        if (!observer) {
            observer = new MutationObserver(redrawAll);
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme', 'data-theme', 'data-color-scheme'] });
        }
    }

    // chartjs:pre-connect - the description, before the chart is made of it.
    preConnect(event) {
        // A function another listener put into the description is kept as it is (structuredClone would refuse it).
        this.source = clone({ data: event.detail.config.data, options: event.detail.config.options });
        Object.assign(event.detail.config, themed(this.source, this.element));
    }

    // chartjs:connect - the chart is drawn; kept to be drawn again in another theme.
    connected(event) {
        this.chart = event.detail.chart;
        charts.set(this.element, { chart: this.chart, controller: this });
    }

    // chartjs:view-value-change - a live component rendered the chart again with new data.
    change(event) {
        this.source = clone({ data: event.detail.data, options: event.detail.options });
        const fresh = themed(this.source, this.element);
        Object.assign(event.detail, fresh);
        if (this.chart) {
            event.detail.data = carriedOver(this.chart.data, fresh.data);
        }
    }

    disconnect() {
        charts.delete(this.element);
        this.chart = null;
    }
}

function redrawAll() {
    for (const { chart, controller } of charts.values()) {
        if (controller.source) {
            const fresh = themed(controller.source, controller.element);
            chart.data = fresh.data;
            chart.options = fresh.options;
            chart.update('none');
        }
    }
}

// The new values put into the datasets of the chart, one by one, so that it moves from what it shows to what it got.
function carriedOver(current, fresh) {
    current.labels = fresh.labels;
    current.datasets = (fresh.datasets ?? []).map((dataset, index) => (current.datasets?.[index] ? Object.assign(current.datasets[index], dataset) : dataset));

    return current;
}

// Colours for the names a chart uses when the theme does not define them (Tailwind's 500s).
const PALETTE = {
    violet: '#8b5cf6', indigo: '#6366f1', blue: '#3b82f6', cyan: '#06b6d4', teal: '#14b8a6', green: '#22c55e',
    yellow: '#eab308', orange: '#f97316', red: '#ef4444', pink: '#ec4899', gray: '#6b7280', primary: '#0d6efd',
};

// A copy of the description with the properties resolved and what the whole chart takes from the page: the colour of
// the text where the canvas is.
function themed(source, element) {
    const token = (name) => readToken(name, element);
    const config = resolveTree(clone(source), token, element);
    const options = config.options && !Array.isArray(config.options) ? config.options : (config.options = {});
    const text = getComputedStyle(element).color || PALETTE.gray;
    // A scheme of its own says whether it is dark (data-color-scheme, set by the theme controller).
    const root = document.documentElement;
    const dark = root.getAttribute('data-color-scheme') === 'dark'
        || ['data-bs-theme', 'data-theme'].some((name) => root.getAttribute(name) === 'dark');
    // What the server said (a font of the design system, a colour of its own: ChartExtension) wins over the page's.
    options.color = options.color ?? withAlpha(text, 0.8);
    options.font = { family: getComputedStyle(document.body).fontFamily, size: 11, weight: 600, ...(options.font ?? {}) };
    options.plugins = options.plugins ?? {};
    // The tooltip in the colours of the page when it has them (--chart-tooltip-bg, -title, -body, -border).
    const own = (name, fallback) => getComputedStyle(element).getPropertyValue(`--chart-tooltip-${name}`).trim() || fallback;
    options.plugins.tooltip = {
        backgroundColor: own('bg', dark ? '#111827' : '#ffffff'),
        titleColor: own('title', dark ? '#f9fafb' : '#111827'),
        bodyColor: own('body', dark ? '#d1d5db' : '#374151'),
        borderColor: own('border', dark ? '#374151' : '#e5e7eb'),
        borderWidth: 1,
        ...(options.plugins.tooltip ?? {}),
    };
    for (const axis of Object.values(options.scales ?? {})) {
        axis.ticks = { color: withAlpha(text, 0.6), ...(axis.ticks ?? {}) };
        axis.grid = { color: withAlpha(text, 0.1), ...(axis.grid ?? {}) };
    }
    // What the application adds, every time: a callback, an option of a plugin of its own.
    element.dispatchEvent(new CustomEvent('chart-theme:options', { detail: { config }, bubbles: true }));

    return config;
}

// A deep copy of plain objects and arrays; anything else (a function, a canvas gradient) as it is.
function clone(node) {
    if (Array.isArray(node)) {
        return node.map(clone);
    }
    if (node && typeof node === 'object' && Object.getPrototypeOf(node) === Object.prototype) {
        return Object.fromEntries(Object.entries(node).map(([key, value]) => [key, clone(value)]));
    }

    return node;
}

function resolveTree(node, token, element) {
    if (typeof node === 'string') {
        return resolve(node, token, element);
    }
    if (Array.isArray(node)) {
        return node.map((item) => resolveTree(item, token, element));
    }
    if (node && typeof node === 'object') {
        for (const key of Object.keys(node)) {
            node[key] = resolveTree(node[key], token, element);
        }
    }

    return node;
}

function resolve(value, token, element) {
    const named = value.match(/^formatter\(([\w.-]+)\)$/);
    if (named) {
        return chartFormatter(named[1], element) ?? undefined;
    }

    const gradient = value.match(/^gradient\((.+)\)$/);
    if (gradient) {
        const colour = resolve(gradient[1], token);

        return (context) => {
            const { chart } = context;
            const area = chart.chartArea;
            if (!area) {
                return withAlpha(colour, 0.2);
            }
            const fill = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
            fill.addColorStop(0, withAlpha(colour, 0.35));
            fill.addColorStop(1, withAlpha(colour, 0));

            return fill;
        };
    }

    if (/^var\(--[\w-]+\)$/.test(value)) {
        return token(value.slice(4, -1));
    }

    return value;
}

function readToken(name, element) {
    return getComputedStyle(element ?? document.documentElement).getPropertyValue(name).trim()
        || PALETTE[name.replace(/^--(bs-)?/, '')]
        || PALETTE.gray;
}

let pixel = null;

function painted(colour) {
    pixel = pixel ?? document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    pixel.clearRect(0, 0, 1, 1);
    pixel.fillStyle = colour;
    pixel.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = pixel.getImageData(0, 0, 1, 1).data;

    return [r, g, b, a / 255];
}

// The colour with another opacity.
function withAlpha(colour, alpha) {
    let r;
    let g;
    let b;
    let a = 1;
    const hex = colour.match(/^#([0-9a-f]{3,8})$/i);
    if (hex) {
        let digits = hex[1];
        if (digits.length <= 4) {
            digits = [...digits].map((d) => d + d).join('');
        }
        [r, g, b] = [0, 2, 4].map((i) => parseInt(digits.slice(i, i + 2), 16));
        if (digits.length === 8) {
            a = parseInt(digits.slice(6, 8), 16) / 255;
        }
    } else {
        const rgb = colour.match(/^rgba?\(([^)]+)\)$/i);
        if (rgb) {
            [r, g, b, a = 1] = rgb[1].split(/[\s,/]+/).filter(Boolean).map(Number);
        } else {
            // Any other notation (oklch() of Tailwind...): as the browser paints it.
            [r, g, b, a] = painted(colour);
        }
    }

    return `rgba(${r}, ${g}, ${b}, ${a * alpha})`;
}
