import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import { openOverlay, closeOverlay } from '../lib/overlay.js';

/*
 * The window of the library (Crud:FileLibrary in a dialog of the layout): opened by pickFiles() for a field, it keeps
 * which files are chosen across the re-renders of the component - another page, a search - and gives them back.
 * A field of one file takes the one clicked at once.
 */
export default class extends Controller {
    static targets = ['file', 'choose', 'count'];
    // Besides aria-pressed: the classes of a chosen file, for a theme that cannot style that (bootstrap5).
    static classes = ['chosen'];

    connect() {
        this.chosen = new Map();
        this.multiple = false;
        this.resolve = null;
    }

    async open({ detail: { token, resolve } }) {
        this.finish([]);
        this.chosen = new Map();
        this.resolve = resolve;
        // Read back from the signed token by the server: here only for how a click behaves.
        this.multiple = new URLSearchParams(token.split('?')[1] ?? '').get('multiple') === '1';
        this.refresh();

        const component = await this.component();
        if (!this.hooked) {
            this.hooked = true;
            component.on('render:finished', () => this.refresh());
        }
        openOverlay(this.element);
        await component.action('open', { token });
    }

    toggle(event) {
        const item = event.currentTarget.closest('[data-file-library-target="file"]');
        const file = JSON.parse(item.dataset.file);
        if (!this.multiple) {
            this.chosen = new Map([[file.ref, file]]);
            this.choose();

            return;
        }
        if (this.chosen.has(file.ref)) {
            this.chosen.delete(file.ref);
        } else {
            this.chosen.set(file.ref, file);
        }
        this.refresh();
    }

    choose() {
        this.finish([...this.chosen.values()]);
        closeOverlay(this.element);
    }

    cancel() {
        closeOverlay(this.element);
    }

    // The dialog is closed however it was: nothing more is chosen.
    closed() {
        this.finish([]);
    }

    fileTargetConnected() {
        this.refresh();
    }

    finish(files) {
        const resolve = this.resolve;
        this.resolve = null;
        resolve?.(files);
    }

    refresh() {
        for (const item of this.fileTargets) {
            const chosen = this.chosen.has(item.dataset.ref);
            const button = item.querySelector('[aria-pressed]');
            button?.setAttribute('aria-pressed', chosen ? 'true' : 'false');
            for (const name of this.chosenClasses) {
                button?.classList.toggle(name, chosen);
            }
        }
        if (this.hasChooseTarget) {
            this.chooseTarget.disabled = this.chosen.size === 0;
        }
        if (this.hasCountTarget) {
            this.countTarget.textContent = this.chosen.size > 0 ? `${this.chosen.size} ✓` : '';
        }
    }

    component() {
        return getComponent(this.element.querySelector('[data-controller~="live"]'));
    }
}
