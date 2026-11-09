import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import { openOverlay } from '../lib/overlay.js';

const EDITING_KEY = 'dashboard-editing';
const SCROLL_KEY = 'dashboard-scroll';

// gridstack is loaded when a dashboard is on the page, not with the bundle: an application without one does not need it.
let gridstackModule = null;

function loadGridStack() {
    gridstackModule ??= import('../vendor/gridstack.js');

    return gridstackModule;
}

/*
 * The grid of the dashboard (@CrudTheme/panel/dashboard.html.twig), on gridstack: 12 columns, rows of 80 px (see the values below), the widgets
 * falling up into free space (float: false). Widgets are moved and resized only in edit mode (E, then Esc);
 * every change is saved as the user's own layout (PUT <panel>/_dashboard/layout) after a short pause. On a narrow screen the
 * widgets are one column and nothing is saved: the layout is the one of a desktop.
 *
 * Adding, removing, settings, reset and "default for everybody" go through the API too, and the page is then loaded
 * again (Turbo) at the same place, still in edit mode when it was.
 *
 * Keyboard in edit mode, on a focused widget: arrows move it, Shift + arrows resize it, Delete removes it.
 * The palette's commands come as browser events: dashboard:edit, dashboard:add, dashboard:reset.
 *
 * The grid can be set with values: data-dashboard-grid-columns-value (12), -cell-height-value (80, in px),
 * -mobile-breakpoint-value (768: one column below that width), -save-delay-value (400, in ms), -margin-value (8, in
 * px). Anything else of gridstack: a listener of "dashboard-grid:options" on the element changes event.detail.options
 * before the grid is made (float, handles of resizing, animation...).
 */
export default class extends Controller {
    static targets = ['grid', 'editButton', 'doneButton', 'addButton', 'resetButton', 'settings', 'search', 'galleryItem', 'galleryCategory'];
    static values = {
        saveUrl: String,
        addUrl: String,
        removeUrl: String,
        resetUrl: String,
        defaultUrl: String,
        failedText: String,
        sharedText: String,
        columns: { type: Number, default: 12 },
        cellHeight: { type: Number, default: 80 },
        mobileBreakpoint: { type: Number, default: 768 },
        saveDelay: { type: Number, default: 400 },
        margin: { type: Number, default: 8 },
    };

    async connect() {
        this.connected = true;
        let GridStack;
        try {
            ({ GridStack } = await loadGridStack());
        } catch (error) {
            console.error('@byfareska/crud: gridstack could not be loaded.', error);

            return;
        }
        // Gone while it was loading.
        if (!this.connected || this.grid) {
            return;
        }
        const options = {
            column: this.columnsValue,
            cellHeight: this.cellHeightValue,
            margin: this.marginValue,
            float: false,
            animate: true,
            // Edit mode may have been asked for (dashboard:edit) while gridstack was loading.
            staticGrid: !this.editing,
            columnOpts: { breakpoints: [{ w: this.mobileBreakpointValue, c: 1 }], layout: 'list' },
            resizable: { handles: 'se' },
            draggable: { cancel: '[data-dashboard-edit], button, a, input, textarea, select' },
        };
        // What an application changes of them, by a listener of its own.
        this.dispatch('options', { detail: { options } });
        this.grid = GridStack.init(options, this.gridTarget);
        this.grid.on('change', () => this.queueSave());

        if (sessionStorage.getItem(this.editingKey()) === '1') {
            this.edit();
        }
        const scroll = Number(sessionStorage.getItem(SCROLL_KEY));
        sessionStorage.removeItem(SCROLL_KEY);
        if (scroll > 0) {
            requestAnimationFrame(() => window.scrollTo(0, scroll));
        }
    }

    disconnect() {
        this.connected = false;
        clearTimeout(this.timer);
        this.grid?.destroy(false);
        this.grid = null;
    }

    // The commands of the palette (dashboard:add, dashboard:reset): what the buttons of the page do.
    openGallery() {
        this.addButtonTarget.click();
    }

    askReset() {
        if (!this.resetButtonTarget.disabled) {
            this.resetButtonTarget.click();
        }
    }

    edit() {
        if (this.editing) {
            return;
        }
        this.editing = true;
        sessionStorage.setItem(this.editingKey(), '1');
        this.gridTarget.classList.add('is-editing');
        this.grid?.setStatic(false);
        this.toggleButtons();
    }

    // Edit mode is remembered for a board (its address of saving), not for every dashboard of the panel.
    editingKey() {
        return `${EDITING_KEY}:${this.saveUrlValue}`;
    }

    done() {
        this.editing = false;
        sessionStorage.removeItem(this.editingKey());
        this.gridTarget.classList.remove('is-editing');
        this.grid?.setStatic(true);
        this.toggleButtons();
        this.save();
    }

    // The buttons of the head and the controls of every widget (data-dashboard-edit: shown only in edit mode).
    toggleButtons() {
        this.editButtonTarget.hidden = this.editButtonTarget.disabled = this.editing;
        this.doneButtonTarget.hidden = this.doneButtonTarget.disabled = !this.editing;
        for (const control of this.gridTarget.querySelectorAll('[data-dashboard-edit]')) {
            control.hidden = !this.editing;
        }
    }

    queueSave() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), this.saveDelayValue);
    }

    async save() {
        clearTimeout(this.timer);
        // One column on a phone: not the layout to keep.
        if (!this.grid || this.grid.getColumn() !== this.columnsValue) {
            return;
        }
        // From the nodes themselves: save() leaves out what gridstack takes for a default (a height of 1...).
        const placements = this.grid.engine.nodes.map(({ id, x, y, w, h }) => ({ id: String(id), x, y, w, h }));
        const signature = JSON.stringify(placements);
        if (signature === this.saved) {
            return;
        }
        if (await this.send('PUT', this.saveUrlValue, { placements })) {
            this.saved = signature;
        }
    }

    async add(event) {
        const widget = event.currentTarget.dataset.widget;
        const item = this.galleryItemTargets.find((element) => element.dataset.widget === widget);
        const size = item?.querySelector('input[type=radio]:checked');
        const body = { widget };
        if (size) {
            body.w = Number(size.dataset.w);
            body.h = Number(size.dataset.h);
        }
        if (await this.send('POST', this.addUrlValue, body)) {
            this.reload();
        }
    }

    // The ids are read from the dataset: as a Stimulus param, an id of digits only would become a number.
    async remove(event) {
        const id = event.currentTarget.dataset.id;
        if (await this.send('DELETE', this.removeUrlValue.replace('__id__', encodeURIComponent(id)))) {
            this.reload();
        }
    }

    async reset() {
        if (await this.send('POST', this.resetUrlValue)) {
            this.reload();
        }
    }

    async share() {
        await this.save();
        if (await this.send('PUT', this.defaultUrlValue)) {
            window.dispatchEvent(new CustomEvent('toast:show', { detail: { title: this.sharedTextValue } }));
            this.reload();
        }
    }

    // The gear of a widget: its settings, in the dialog of the page (Panel:WidgetSettings, a live component).
    async settings(event) {
        // Read before anything is awaited: currentTarget is the button only while the event is dispatched.
        const id = event.currentTarget.dataset.id;
        const component = await getComponent(this.settingsTarget);
        await component.action('open', { id });
        openOverlay(this.settingsTarget.closest('.modal, dialog'));
    }

    // dashboard:changed - the settings of a widget were saved.
    changed() {
        this.reload();
    }

    filter() {
        const query = this.searchTarget.value.trim().toLocaleLowerCase();
        for (const item of this.galleryItemTargets) {
            item.hidden = query !== '' && !item.dataset.search.includes(query);
        }
        for (const category of this.galleryCategoryTargets) {
            category.hidden = category.querySelectorAll('[data-dashboard-grid-target="galleryItem"]:not([hidden])').length === 0;
        }
    }

    key(event) {
        if (!this.editing || !this.grid || !event.target.classList?.contains('grid-stack-item')) {
            return;
        }
        const item = event.target;
        const node = item.gridstackNode;
        if (!node) {
            return;
        }
        if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            item.querySelector('[data-dashboard-remove]')?.click();

            return;
        }
        const step = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[event.key];
        if (!step) {
            return;
        }
        event.preventDefault();
        const [dx, dy] = step;
        if (event.shiftKey) {
            this.grid.update(item, { w: Math.max(1, node.w + dx), h: Math.max(1, node.h + dy) });
        } else {
            this.grid.update(item, { x: Math.max(0, node.x + dx), y: Math.max(0, node.y + dy) });
        }
        item.focus();
    }

    async send(method, url, body = null) {
        try {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                // A header of its own: a page of another site cannot send it without asking first (CORS), so the
                // endpoints refuse a request without it (DashboardController).
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(body ? { 'Content-Type': 'application/json' } : {}),
                },
                body: body ? JSON.stringify(body) : null,
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return true;
        } catch {
            window.dispatchEvent(new CustomEvent('toast:show', { detail: { title: this.failedTextValue, variant: 'danger' } }));

            return false;
        }
    }

    reload() {
        sessionStorage.setItem(SCROLL_KEY, String(window.scrollY));
        if (window.Turbo) {
            window.Turbo.visit(window.location.href, { action: 'replace' });
        } else {
            window.location.reload();
        }
    }
}
