import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
const FOCUSABLE = 'a[href], button, input:not([type=hidden]), select, textarea, summary, [tabindex]:not([tabindex="-1"])';

const visible = (element) => element.checkVisibility?.({ visibilityProperty: true }) ?? true;

/*
 * The tabs of a page laid out in tabs (@CrudTheme/crud/layout.html.twig), worked with the keyboard as well:
 *
 * - on the tab bar ← / → go to the tab before / after (round), Home / End to the first / last;
 * - in a form, Tab on the last field of a tab goes on to the first one of the next tab, Shift + Tab on the first
 *   back to the last one of the previous tab, so the whole form is filled in one go.
 *
 * The tab in view is kept where the next page looks for it: in the address (?tab=<name>), in the links to another
 * page of the same record (data-tabs-link) and, in a live component, in its "tab" property, so that a re-render
 * does not go back to the first tab. data-tabs-url-value="false" leaves the address and the links alone;
 * data-tabs-prop-value="…" names another property of the live component.
 *
 * Every switch is told with a "tabs:change" event on the element of the controller (detail: name, tab, pane).
 * A tab button that is hidden (the show-if controller hides the tab of a pane it hides) is passed over by the keys.
 *
 * <div data-controller="tabs">
 *     <div role="tablist"><button role="tab" data-tabs-target="tab" data-tabs-name-param="account" aria-controls="…">…
 *     <div role="tabpanel" data-tabs-target="pane" id="…">…
 */
export default class extends Controller {
    static targets = ['tab', 'pane'];
    static values = {
        url: { type: Boolean, default: true },
        prop: { type: String, default: 'tab' },
    };

    async connect() {
        this.sync = this.sync.bind(this);
        this.press = this.press.bind(this);
        window.addEventListener('keydown', this.press);

        const root = this.element.closest('[data-controller~="live"]');

        if (root) {
            this.component = await getComponent(root);
            // A refused save may show another tab than the one asked for: the first one with an error.
            this.component.on('render:finished', this.sync);
        }
    }

    disconnect() {
        window.removeEventListener('keydown', this.press);
        this.component?.off('render:finished', this.sync);
    }

    // A click on a tab.
    select({ currentTarget }) {
        this.show(currentTarget);
    }

    // The keys of the tab bar.
    walk(event) {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }

        const tabs = this.tabTargets.filter((tab) => !tab.hidden);
        const current = tabs.indexOf(event.currentTarget);
        const next = {
            ArrowLeft: tabs[(current - 1 + tabs.length) % tabs.length],
            ArrowRight: tabs[(current + 1) % tabs.length],
            Home: tabs[0],
            End: tabs.at(-1),
        }[event.key];

        if (next) {
            event.preventDefault();
            this.show(next);
            next.focus();
        }
    }

    // Tab, from anywhere on the page.
    press(event) {
        if (event.key !== 'Tab' || event.defaultPrevented || event.isComposing || event.altKey || event.ctrlKey || event.metaKey || !visible(this.element)) {
            return;
        }

        this.flow(event);
    }

    // Tab past the end of a tab of a form goes on in the next one.
    flow(event) {
        const pane = this.paneTargets.find((candidate) => candidate.contains(event.target));

        if (!pane || !this.inForm()) {
            return;
        }

        const fields = this.focusables(pane);
        const step = event.shiftKey ? -1 : 1;

        if (event.target !== fields.at(step > 0 ? -1 : 0)) {
            return;
        }

        // The next tab in that direction that is not hidden.
        let index = this.paneTargets.indexOf(pane) + step;
        while (this.tabTargets[index]?.hidden) {
            index += step;
        }
        const tab = this.tabTargets[index];
        const into = tab ? this.focusables(this.paneOf(tab)) : [];

        if (!tab || into.length === 0) {
            return;
        }

        event.preventDefault();
        this.show(tab);
        into.at(step > 0 ? 0 : -1).focus();
    }

    show(tab) {
        for (const each of this.tabTargets) {
            const active = each === tab;

            each.classList.toggle('active', active);
            each.setAttribute('aria-selected', active ? 'true' : 'false');
            each.tabIndex = active ? 0 : -1;
            const pane = this.paneOf(each);
            if (pane) {
                pane.classList.toggle('active', active);
                pane.hidden = !active;
            }
        }

        this.remember(tab.dataset.tabsNameParam);
        this.dispatch('change', { prefix: 'tabs', detail: { name: tab.dataset.tabsNameParam, tab, pane: this.paneOf(tab) ?? null } });
    }

    sync() {
        const name = this.tabTargets.find((tab) => tab.classList.contains('active'))?.dataset.tabsNameParam;

        if (name && name !== this.component?.getData(this.propValue)) {
            this.remember(name);
        }
    }

    remember(name) {
        this.component?.set(this.propValue, name, false);

        if (!this.urlValue) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('tab', name);
        window.history.replaceState(window.history.state, '', url);

        for (const link of document.querySelectorAll('a[data-tabs-link]')) {
            const href = new URL(link.href);
            href.searchParams.set('tab', name);
            link.href = href.toString();
        }
    }

    paneOf(tab) {
        return this.paneTargets.find((pane) => pane.id === tab.getAttribute('aria-controls'));
    }

    focusables(pane) {
        // Shown once the tab is: what is folded away or switched off is passed over.
        const wasActive = pane.classList.contains('active');
        const wasHidden = pane.hidden;
        pane.classList.add('active');
        pane.hidden = false;
        const found = [...pane.querySelectorAll(FOCUSABLE)].filter((element) => !element.disabled && visible(element));
        pane.classList.toggle('active', wasActive);
        pane.hidden = wasHidden;

        return found;
    }

    inForm() {
        return this.element.closest('form') !== null;
    }
}
