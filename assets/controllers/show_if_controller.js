import { Controller } from '@hotwired/stimulus';

/*
 * Fields of a form shown only where they mean something: a row with data-show-if="trigger=schedule; schedule=daily
 * weekly" is shown while the field named "…[trigger]" has the value "schedule" and the one named "…[schedule]" one
 * of "daily", "weekly" - every condition, each any of its values. Hidden ones keep their values (the use case leaves
 * out what does not count), and so is a group of the layout (.crud-fieldset) whose every field is. On the generic CRUD
 * form (Crud:Form); after a live render the rows are told again.
 *
 * A condition is one of:
 *
 *   field=a b      the field has one of the values (field= : it is empty)
 *   field!=a b     it has none of them
 *   field:filled   it has a value (a checkbox: it is ticked)
 *   field:empty    it has none
 *
 * Conditions separated by ";" must all hold; groups of them separated by "||" are alternatives, any one will do:
 * data-show-if="kind=email; email:filled || kind=sms". A field of several values - checkboxes named "…[field][]",
 * a <select multiple> - has a value of the list for "=" when any ticked one is, and none for "!=" when none is.
 *
 * A row that is a pane of the tabs (its id is the aria-controls of a tab button) is not hidden itself - the tabs
 * controller shows one pane at a time: its tab button is. When that was the active tab, the first one still in view
 * is opened instead.
 *
 * <div data-controller="show-if">
 *     <select name="dto[trigger]">…</select>
 *     <div data-show-if="trigger=schedule">…</div>
 */
export default class extends Controller {
    connect() {
        this.apply = this.apply.bind(this);
        this.element.addEventListener('change', this.apply);
        // A text field typed into ("field:filled") tells it before it is left.
        this.element.addEventListener('input', this.apply);
        // A live render puts new rows in, shown again, and morphs away what was hidden (an attribute only).
        this.observer = new MutationObserver(() => this.apply());
        this.observer.observe(this.element, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
        this.apply();
    }

    disconnect() {
        this.element.removeEventListener('change', this.apply);
        this.element.removeEventListener('input', this.apply);
        this.observer?.disconnect();
    }

    apply() {
        const failed = new Set();
        for (const row of this.element.querySelectorAll('[data-show-if]')) {
            const shown = this.holds(row.dataset.showIf);
            if (!shown) {
                failed.add(row);
            }
            const tab = this.tabOf(row);
            if (tab) {
                this.toggleTab(tab, shown);
            } else if (row.hidden === shown) {
                row.hidden = !shown;
            }
        }
        // A group of the layout left with no field in view goes too (its heading would stand over nothing).
        for (const group of this.element.querySelectorAll('.crud-fieldset')) {
            const fields = [...group.querySelectorAll('input:not([type=hidden]), select, textarea')];
            const empty = fields.length > 0 && fields.every((field) => this.within(field, failed));
            if (group.hidden !== empty) {
                group.hidden = empty;
            }
        }
    }

    // "a=1; b=2 || c=3": any group, every condition of it.
    holds(expression) {
        return expression.split('||').some((group) => group.split(';').map((part) => part.trim()).filter(Boolean)
            .every((condition) => this.test(condition)));
    }

    test(condition) {
        const state = condition.match(/^(.+?):(filled|empty)$/);
        if (state) {
            const filled = this.read(state[1].trim()).filled;

            return state[2] === 'filled' ? filled : !filled;
        }
        const comparison = condition.match(/^(.+?)(!=|=)(.*)$/);
        if (!comparison) {
            // Not a condition: it does not hide anything.
            return true;
        }
        const listed = comparison[3].trim().split(/\s+/);
        const { values } = this.read(comparison[1].trim());
        const any = (values.length > 0 ? values : ['']).some((value) => listed.includes(value));

        return comparison[2] === '=' ? any : !any;
    }

    // The values of the field (several for checkboxes "…[field][]" and a <select multiple>), and whether it has any.
    read(field) {
        const inputs = [...this.element.querySelectorAll(
            `[name$="[${field}]"], [name="${field}"], [name$="[${field}][]"], [name="${field}[]"]`,
        )];
        if (inputs.length === 0) {
            return { values: [''], filled: false };
        }
        const multiple = inputs.some((one) => one.name.endsWith('[]') || (one instanceof HTMLSelectElement && one.multiple));
        if (multiple) {
            const values = inputs.flatMap((one) => {
                if (one instanceof HTMLSelectElement) {
                    return [...one.selectedOptions].map((option) => option.value);
                }
                if (one.type === 'checkbox' || one.type === 'radio') {
                    return one.checked ? [one.value] : [];
                }

                return [one.value];
            }).filter((value) => value !== '');

            return { values, filled: values.length > 0 };
        }
        const input = inputs.find((one) => (one.type !== 'radio' && one.type !== 'checkbox') || one.checked) ?? inputs[0];
        if (input.type === 'checkbox') {
            return { values: [input.checked ? '1' : '0'], filled: input.checked };
        }
        // A group of radios with none chosen has no value.
        const value = input.type === 'radio' && !input.checked ? '' : input.value;

        return { values: [value], filled: value.trim() !== '' };
    }

    // The tab button of a pane of the tabs, if the row is one.
    tabOf(row) {
        if (!row.id) {
            return null;
        }

        return document.querySelector(`[role="tab"][aria-controls="${CSS.escape(row.id)}"]`);
    }

    toggleTab(tab, shown) {
        if (tab.hidden === shown) {
            tab.hidden = !shown;
        }
        if (shown || tab.getAttribute('aria-selected') !== 'true') {
            return;
        }
        // The tab in view went: the first one still there is opened (through the tabs controller, as by a click).
        const first = [...(tab.closest('[role="tablist"]') ?? document).querySelectorAll('[role="tab"]')]
            .find((other) => !other.hidden && !other.disabled);
        first?.click();
    }

    // In a row whose condition does not hold, however deep.
    within(field, failed) {
        for (let row = field.closest('[data-show-if]'); row; row = row.parentElement?.closest('[data-show-if]')) {
            if (failed.has(row)) {
                return true;
            }
        }

        return false;
    }
}
