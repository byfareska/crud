import { Controller } from '@hotwired/stimulus';

/*
 * A checkbox that stands for several, some of them checked: shown neither checked nor not. The state is a property,
 * not an attribute, so the markup says it in a value and this sets it - again whenever a live render changes it.
 *
 * <input type="checkbox" data-controller="indeterminate" data-indeterminate-mixed-value="true">
 */
export default class extends Controller {
    static values = { mixed: Boolean };

    mixedValueChanged() {
        this.element.indeterminate = this.mixedValue;
    }
}
