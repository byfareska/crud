import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * The field of a filter with a choice of how it compares (ComparisonType): choosing it changes the field in the browser
 * - "between" shows the second value, the hints "from" / "to" and a range in the calendar; "is one of" a multiple
 * choice in place of the value; "is empty" no value at all - and asks the server only when there is something to
 * compare (always for "is empty"), so the list changes.
 *
 * <div data-controller="comparison">
 *     <select data-model="norender|filters.stock.comparison" data-comparison-target="select"
 *             data-action="comparison#change">…</select>
 *     <input data-model="filters.stock.value" data-comparison-target="value hinted" data-hint="From">
 *     <span data-comparison-target="between">–</span>
 *     <input data-model="filters.stock.to" data-comparison-target="value hinted between" data-hint="To">
 *     <div data-controller="date-range" data-comparison-target="dates" data-placeholder="From — To">…</div>
 * </div>
 *
 * What it changes it changes again after each render, from the choice the server rendered: the live component keeps
 * changes made in the browser to the attributes of its elements.
 */
export default class extends Controller {
    static targets = ['select', 'between', 'hinted', 'value', 'dates', 'single', 'many'];
    static values = {
        between: { type: String, default: 'between' },
        // Several values at once: the multiple choice in place of the one value.
        many: { type: Array, default: ['in', 'not_in'] },
        // Nothing to type: the list changes as soon as it is chosen.
        presence: { type: Array, default: ['empty', 'filled'] },
    };

    async connect() {
        this.sync();
        this.synced = () => this.sync();
        const root = this.element.closest('[data-controller~="live"]');
        if (!root) {
            return;
        }
        this.component = await getComponent(root);
        this.component.on('render:finished', this.synced);
    }

    disconnect() {
        this.component?.off('render:finished', this.synced);
    }

    change() {
        this.sync();
        const presence = this.presenceValue.includes(this.selectTarget.value);
        if (!presence && !this.valueTargets.some((input) => input.value !== '')) {
            return;
        }
        // After the live component took the choice (norender): its listener comes after this one.
        setTimeout(() => this.component?.render());
    }

    sync() {
        if (!this.hasSelectTarget) {
            return;
        }
        const between = this.selectTarget.value === this.betweenValue;
        const many = this.manyValue.includes(this.selectTarget.value);
        const presence = this.presenceValue.includes(this.selectTarget.value);
        for (const element of this.singleTargets) {
            element.hidden = many || presence;
        }
        for (const element of this.manyTargets) {
            element.hidden = !many;
        }
        for (const element of this.betweenTargets) {
            element.hidden = !between;
        }
        for (const input of this.hintedTargets) {
            const hint = between ? input.dataset.hint ?? '' : '';
            input.placeholder = hint;
            input.title = hint;
        }
        for (const dates of this.datesTargets) {
            dates.dataset.dateRangeRangeValue = String(between);
            dates.dataset.dateRangePlaceholderValue = between ? dates.dataset.placeholder ?? '' : '';
        }
    }
}
