import { Controller } from '@hotwired/stimulus';

/*
 * Shows a message left for the next page (a session flash) in the panel toast:
 *
 * <span hidden data-controller="flash" data-flash-title-value="Zapisano"></span>
 *
 * data-flash-variant-value: the variant of the toast (success, danger, warning, info).
 */
export default class extends Controller {
    static values = { title: String, variant: String };

    connect() {
        // After the current task, so that the toast controller is connected too on a full page load.
        setTimeout(() => {
            window.dispatchEvent(new CustomEvent('toast:show', { detail: { title: this.titleValue, ...(this.variantValue ? { variant: this.variantValue } : {}) } }));
            this.element.remove();
        });
    }
}
