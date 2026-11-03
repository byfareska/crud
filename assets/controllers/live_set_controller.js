import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * Writes what is typed into a field to a LiveProp of the surrounding live component, a moment after the typing
 * stops (or at once on Enter), then empties the field and keeps the cursor in it.
 * Unlike data-model it does not bind the field: the component never writes the prop back into it.
 * Dispatches "live-set:set" when it does so.
 *
 * <input type="number" id="page-jump" data-controller="live-set" data-live-set-model-value="page"
 *        data-action="input->live-set#later change->live-set#now">
 *
 * The id lets the field be found again when the re-render replaced it. With data-live-set-keep-value="true" the field
 * keeps what was chosen - a select of how many records a page has, whose prop is null while it is the first one.
 */
export default class extends Controller {
    static values = { model: String, delay: { type: Number, default: 600 }, keep: Boolean };

    disconnect() {
        clearTimeout(this.timer);
    }

    later() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.now(), this.delayValue);
    }

    async now() {
        clearTimeout(this.timer);

        const sent = this.element.value;
        if (sent === '' || sent === this.sending) {
            return;
        }
        this.sending = sent;

        const root = this.element.closest('[data-controller~="live"]');
        const id = this.element.id;
        const hadFocus = document.activeElement === this.element;
        const component = await getComponent(root);

        this.dispatch('set', { detail: { value: sent } });
        await component.set(this.modelValue, sent, true);
        this.sending = null;

        // The field may be a new element now, e.g. when it moved to the other side of the current page.
        const field = this.element.isConnected ? this.element : (id && document.getElementById(id));
        if (!field) {
            return;
        }
        // Emptied for the next number - unless more has been typed while the answer was on its way.
        if (!this.keepValue && field.value === sent) {
            field.value = '';
        }
        if (hadFocus) {
            field.focus({ preventScroll: true });
        }
    }
}
