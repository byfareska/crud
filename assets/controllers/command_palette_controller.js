import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import { closeOverlay } from '../lib/overlay.js';
import { readJson, writeJson } from '../lib/storage.js';

const RECENT_KEY = 'command-palette-recent';
const RECENT_LIMIT = 5;

/*
 * Keyboard and browser-side behaviour of the command palette. Searching and rendering the results is
 * done by the Panel:CommandPalette live component inside; this controller owns what the server cannot:
 * arrow-key navigation, recently used items and commands that run in the browser.
 *
 * Results are links. A result with data-client-action="some:event" dispatches that event on window
 * instead of (or, when it points to another page, after) navigating.
 *
 * The recent items are kept in localStorage under a key of the user and the panel: data-command-palette-storage-key-value
 * on the results (Panel:CommandPalette renders it, so it follows the panel the palette is in) or on the element of the
 * controller (which wins). Without either, the one key of old: "command-palette-recent".
 */
export default class extends Controller {
    static targets = ['content', 'input', 'item'];
    static values = { storageKey: String };

    async connect() {
        this.activateFirst = () => this.activate(0);
        this.active = 0;

        this.component = await getComponent(this.contentTarget);
        this.component.on('render:finished', this.activateFirst);
        this.activateFirst();
    }

    disconnect() {
        this.component?.off('render:finished', this.activateFirst);
    }

    // overlay:show: every session starts from an empty query, the current list of recent items and the panel of the
    // page (data-scope of the shell: the modal is kept across Turbo visits, also into the other panel).
    reset(event) {
        if (!this.component || event?.target !== this.element) {
            return;
        }

        this.inputTarget.value = '';
        const storageKey = this.storageKey();
        this.component.set('query', '', false);
        this.component.set('recent', readJson(storageKey, []), false);
        this.component.set('scope', document.querySelector('[data-scope]')?.dataset.scope ?? '', false);
        this.component.render().then(() => {
            // Another panel has a key of its own (rendered with the new scope): its recent items, once more.
            if (this.storageKey() !== storageKey) {
                this.component.set('recent', readJson(this.storageKey(), []), true).catch(() => {});
            }
        }, () => {});
    }

    // overlay:shown
    focus() {
        this.inputTarget.focus();
    }

    next() {
        this.activate(this.active + 1);
    }

    previous() {
        this.activate(this.active - 1);
    }

    submit() {
        this.itemTargets[this.active]?.click();
    }

    hover({ currentTarget }) {
        this.activate(this.itemTargets.indexOf(currentTarget), false);
    }

    choose(event) {
        const item = event.currentTarget;
        const clientAction = item.dataset.clientAction;
        const href = item.getAttribute('href');

        this.remember(item.dataset.key);
        closeOverlay(this.element);

        if (!clientAction) {
            return; // A plain link: Turbo takes it from here.
        }

        const run = () => window.dispatchEvent(new CustomEvent(clientAction));

        if (href === '#' || new URL(href, window.location.href).pathname === window.location.pathname) {
            event.preventDefault();
            run();
        } else {
            // The command belongs to another page: let Turbo go there, then run it.
            document.addEventListener('turbo:load', run, { once: true });
        }
    }

    activate(index, scroll = true) {
        const items = this.itemTargets;

        if (items.length === 0) {
            this.active = 0;

            return;
        }

        this.active = (index + items.length) % items.length;
        items.forEach((item, position) => {
            item.classList.toggle('active', position === this.active);
            item.toggleAttribute('data-active', position === this.active);
            item.setAttribute('aria-selected', position === this.active ? 'true' : 'false');
        });

        if (scroll) {
            items[this.active].scrollIntoView({ block: 'nearest' });
        }
    }

    // By key: the name of an item depends on the language. Results without one (stored records) are not remembered.
    remember(key) {
        if (!key) {
            return;
        }

        const storageKey = this.storageKey();
        const recent = readJson(storageKey, []).filter((other) => other !== key);

        writeJson(storageKey, [key, ...recent].slice(0, RECENT_LIMIT));
    }

    storageKey() {
        if (this.storageKeyValue) {
            return this.storageKeyValue;
        }

        return (this.hasContentTarget && this.contentTarget.dataset.commandPaletteStorageKeyValue) || RECENT_KEY;
    }
}
