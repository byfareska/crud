import { Controller } from '@hotwired/stimulus';

/*
 * A menu without Bootstrap: a <details>, its <summary> the button. Open, it is put under the button with
 * position: fixed - so a table that scrolls does not cut it - above it when there is no room below; a click outside,
 * Esc, scrolling or choosing an item closes it. Only one is open at a time.
 *
 * <details data-dropdown data-controller="dropdown" data-action="toggle->dropdown#toggle keydown.esc->dropdown#escape">
 *     <summary>…</summary>
 *     <div data-dropdown-target="menu" data-action="click->dropdown#choose">…</div>
 * </details>
 */
export default class extends Controller {
    static targets = ['menu'];

    connect() {
        this.outside = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        // Not when what scrolls is the menu itself.
        this.away = (event) => {
            if (!(event.target instanceof Node && this.element.contains(event.target))) {
                this.close();
            }
        };
    }

    disconnect() {
        this.unlisten();
    }

    toggle() {
        if (!this.element.open) {
            this.unlisten();

            return;
        }
        for (const other of document.querySelectorAll('details[data-dropdown][open]')) {
            if (other !== this.element) {
                other.open = false;
            }
        }
        this.place();
        document.addEventListener('click', this.outside, true);
        window.addEventListener('scroll', this.away, { capture: true, passive: true });
        window.addEventListener('resize', this.away);
    }

    escape(event) {
        if (this.element.open) {
            event.preventDefault();
            event.stopPropagation();
            this.close();
            this.element.querySelector('summary')?.focus();
        }
    }

    choose(event) {
        if (event.target.closest('a[href], button')) {
            this.close();
        }
    }

    close() {
        this.element.open = false;
    }

    place() {
        if (!this.hasMenuTarget) {
            return;
        }
        const button = this.element.querySelector('summary').getBoundingClientRect();
        const menu = this.menuTarget;
        Object.assign(menu.style, { position: 'fixed', top: `${button.bottom + 4}px`, right: `${window.innerWidth - button.right}px`, left: 'auto' });
        const height = menu.getBoundingClientRect().height;
        if (button.bottom + 4 + height > window.innerHeight && button.top - 4 - height > 0) {
            menu.style.top = `${button.top - 4 - height}px`;
        }
    }

    unlisten() {
        document.removeEventListener('click', this.outside, true);
        window.removeEventListener('scroll', this.away, { capture: true });
        window.removeEventListener('resize', this.away);
    }
}
