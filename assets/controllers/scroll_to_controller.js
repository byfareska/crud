import { Controller } from '@hotwired/stimulus';

/*
 * Brings an ancestor back into view, e.g. the top of a table when its pager at the bottom is used:
 *
 * <ul data-controller="scroll-to" data-scroll-to-selector-value=".panel"
 *     data-action="click->scroll-to#scroll live-set:set->scroll-to#scroll">
 */
export default class extends Controller {
    static values = { selector: String };

    scroll(event) {
        // Of the clicks only those on a button that does something count: not the ones between the controls,
        // and not the one that puts the cursor into a field.
        if (event.type === 'click') {
            const button = event.target.closest('button');
            if (!button || button.disabled) {
                return;
            }
        }

        const target = this.element.closest(this.selectorValue);
        // Only when its top has been scrolled away: nothing moves on a short list.
        if (target && target.getBoundingClientRect().top < 0) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}
