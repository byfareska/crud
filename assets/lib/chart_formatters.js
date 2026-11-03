/*
 * Functions of a chart the server cannot write in JSON - a tick of an axis in "1 234,50 zł", a tooltip in % - by a name
 * the description of the chart gives instead: "formatter(currency)" anywhere a function goes
 * (options.scales.y.ticks.callback, options.plugins.tooltip.callbacks.label...). The application names them once:
 *
 *   import { registerChartFormatter } from '@byfareska/crud';
 *   registerChartFormatter('currency', () => (value) => new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(value));
 *
 * The factory is called for every chart it is used in, given the element of the chart, so it may read data-* of it.
 */
const formatters = new Map();

export function registerChartFormatter(name, factory) {
    formatters.set(name, factory);
}

// The function of a name, made for the chart of the element; null when nothing is registered under it.
export function chartFormatter(name, element) {
    const factory = formatters.get(name);
    if (!factory) {
        console.warn(`@byfareska/crud: no chart formatter "${name}" (registerChartFormatter()).`);

        return null;
    }

    return factory(element);
}
