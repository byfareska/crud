import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

const FIELD = 'input:not([type=hidden]), select, textarea';
// A field with an error: Bootstrap's class, or the attribute any form theme may set.
const INVALID = '.is-invalid, [aria-invalid="true"]';

const visible = (element) => element.checkVisibility?.({ visibilityProperty: true }) ?? true;

/*
 * Puts the focus where the typing starts, so a page reached with a key can be used without the mouse:
 * on the first field of a form and, when a live component comes back with errors, on the first field that has one -
 * of those that can be seen: not in a tab or a folded fieldset out of view.
 *
 * <div data-controller="focus-first" {{ attributes }}> … <form>…</form> </div>
 */
export default class extends Controller {
    async connect() {
        this.focusInvalid = () => this.focus(INVALID);

        // Not where the focus would only bring up the on-screen keyboard over the form.
        if (!window.matchMedia('(hover: none)').matches) {
            this.focus(INVALID) || this.focus('');
        }

        if (this.element.matches('[data-controller~="live"]')) {
            this.component = await getComponent(this.element);
            this.component.on('render:finished', this.focusInvalid);
        }
    }

    disconnect() {
        this.component?.off('render:finished', this.focusInvalid);
    }

    focus(state) {
        const field = [...this.element.querySelectorAll(FIELD)]
            .find((candidate) => !candidate.disabled && (state === '' || candidate.matches(state)) && visible(candidate));

        field?.focus();

        return field !== undefined;
    }
}
