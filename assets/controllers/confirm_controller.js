import { Controller } from '@hotwired/stimulus';
import { closeMenus, closeOverlay, openOverlay } from '../lib/overlay.js';

/*
 * Asks before the actions that follow it on the same element run:
 *
 * <button data-controller="confirm" data-confirm-message-value="Usunąć?" data-confirm-hint-value="Tego nie da się cofnąć."
 *         data-confirm-accept-value="Usuń" data-action="confirm#ask live#action" data-live-action-param="delete">
 *
 * The question is put in the page's one dialog (Ui:ConfirmDialog, rendered by the layout). The click is held back
 * and, when the answer is yes, made once more - so whatever else listens for it needs to know nothing about this.
 * The hint and the text of the "yes" button are optional.
 *
 * The answer is told on the element with a "confirm:accepted" or "confirm:cancelled" event, once the dialog is closed
 * (before the click is made again on "yes").
 *
 * With data-confirm-input-value ({"label": "Powód", "required": true, "choices": [{"label": "…", "value": "…"}]}) the
 * dialog asks for a text - or a choice - too: what was typed goes onto the element as the parameter of its action
 * (data-live-input-param, or the one named by data-confirm-param-value) before the click is made again, so the live
 * action gets it as an argument. "Yes" without a required one is not taken.
 */
export default class extends Controller {
    static values = {
        message: String,
        hint: String,
        accept: String,
        input: Object,
        param: { type: String, default: 'input' },
        dialog: { type: String, default: '#confirm-dialog' },
    };

    disconnect() {
        if (this.dialog) {
            closeOverlay(this.dialog);
        }
    }

    ask(event) {
        if (this.confirmed) {
            this.confirmed = false;

            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const dialog = document.querySelector(this.dialogValue);

        if (!dialog || this.dialog) {
            return;
        }

        const accept = dialog.querySelector('[data-confirm-dialog="accept"]');
        const hint = dialog.querySelector('[data-confirm-dialog="hint"]');
        const field = this.prepareField(dialog);
        const focused = document.activeElement;
        let accepted = false;
        const onAccept = (event) => {
            // A required answer not given: the dialog stays open, saying why.
            if (field && !field.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                field.reportValidity();

                return;
            }
            accepted = true;
        };

        dialog.querySelector('[data-confirm-dialog="message"]').textContent = this.messageValue;
        hint.textContent = this.hintValue;
        hint.hidden = !this.hasHintValue;
        accept.textContent = this.hasAcceptValue ? this.acceptValue : accept.dataset.confirmDialogLabel;
        accept.addEventListener('click', onAccept);

        dialog.addEventListener('overlay:hidden', () => {
            accept.removeEventListener('click', onAccept);
            this.dialog = null;

            if (!this.element.isConnected) {
                return;
            }

            this.returnFocus(focused);
            this.dispatch(accepted ? 'accepted' : 'cancelled', { prefix: 'confirm' });

            if (accepted) {
                if (field) {
                    // As the parameter of the action that follows: data-live-input-param.
                    const name = this.paramValue.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
                    this.element.dataset[`live${name.charAt(0).toUpperCase()}${name.slice(1)}Param`] = field.value;
                }
                this.confirmed = true;
                this.element.click();
            }
        }, { once: true });

        // The held-back click never reached the menu the element may sit in, so it is still open.
        closeMenus();

        this.dialog = dialog;
        openOverlay(dialog);
    }

    // The field of the dialog for what the question asks besides "yes": shown, emptied, a text or a choice; null when
    // it asks nothing more (and then it is hidden).
    prepareField(dialog) {
        const wrapper = dialog.querySelector('[data-confirm-dialog="field"]');
        if (!wrapper) {
            return null;
        }
        const text = wrapper.querySelector('[data-confirm-dialog="text"]');
        const choice = wrapper.querySelector('[data-confirm-dialog="choice"]');
        // A dialog of an older theme has no input of its own: a number or a day is typed into the text then.
        const input = wrapper.querySelector('[data-confirm-dialog="input"]');
        const label = wrapper.querySelector('[data-confirm-dialog="label"]');
        const fields = [text, choice, input].filter(Boolean);
        wrapper.hidden = !this.hasInputValue;
        if (!this.hasInputValue) {
            for (const one of fields) {
                one.required = false;
            }

            return null;
        }

        const choices = Array.isArray(this.inputValue.choices) ? this.inputValue.choices : [];
        // A text (several lines), or the input of its kind: a number, a day, a line.
        const kind = this.inputValue.type ?? 'text';
        const field = choices.length > 0 ? choice : (kind !== 'text' && input ? input : text);
        for (const other of fields.filter((one) => one !== field)) {
            other.hidden = true;
            other.required = false;
        }
        if (field === input) {
            input.type = { number: 'number', date: 'date' }[kind] ?? 'text';
        }
        field.hidden = false;
        field.required = this.inputValue.required !== false;
        field.value = '';
        label.textContent = this.inputValue.label ?? '';
        label.htmlFor = field.id;
        if (field === choice) {
            choice.replaceChildren(new Option('', ''), ...choices.map((item) => new Option(item.label, item.value)));
        }

        return field;
    }

    // Back to where the focus was, or - when that was an item of a menu closed since - to what holds the element (a row of a list).
    returnFocus(focused) {
        const visible = (element) => element instanceof HTMLElement && element.isConnected && element.getClientRects().length > 0;
        const target = visible(focused) && focused !== document.body ? focused : this.element.parentElement?.closest('[tabindex]');

        target?.focus();
    }
}
