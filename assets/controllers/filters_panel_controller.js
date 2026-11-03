import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * Opens and closes the panel of the filters in the browser: every filter is rendered, those of the panel hidden while
 * it is closed. The live component is told (the LiveProp filtersOpen, no render), so its next render keeps it so.
 *
 * <section data-controller="filters-panel" data-filters-panel-shown-value="false"
 *          data-filters-panel-open-class="btn-secondary" data-filters-panel-closed-class="btn-outline-secondary">
 *     <button data-filters-panel-target="toggle" data-action="filters-panel#toggle" aria-expanded="false">…</button>
 *     <div data-filters-panel-target="box" data-always="true">
 *         <div>a filter always in view</div>
 *         <div data-filters-panel-target="panel" hidden>a filter of the panel</div>
 *     </div>
 * </section>
 *
 * The shown value is what the server rendered; the controller does not write it (nor anything the server renders
 * otherwise): the live component would keep that change and lay it over what a later render says. What it changes it
 * changes again after each render.
 */
export default class extends Controller {
    static targets = ['toggle', 'panel', 'box'];
    static values = { shown: Boolean, prop: { type: String, default: 'filtersOpen' } };
    static classes = ['open', 'closed'];

    async connect() {
        this.synced = () => this.sync();
        const root = this.element.closest('[data-controller~="live"]');
        if (!root) {
            return;
        }
        this.component = await getComponent(root);
        this.component.on('render:finished', this.synced);
    }

    disconnect() {
        this.component?.off('render:finished', this.synced);
    }

    shownValueChanged() {
        this.open = this.shownValue;
        this.sync();
    }

    toggle() {
        this.open = !this.open;
        this.component?.set(this.propValue, this.open, false);
        this.sync();
    }

    sync() {
        for (const element of this.panelTargets) {
            element.hidden = !this.open;
        }
        for (const box of this.boxTargets) {
            box.hidden = !this.open && box.dataset.always !== 'true';
        }
        for (const button of this.toggleTargets) {
            button.setAttribute('aria-expanded', String(this.open));
            if (this.hasOpenClass && this.hasClosedClass) {
                button.classList.remove(...(this.open ? this.closedClasses : this.openClasses));
                button.classList.add(...(this.open ? this.openClasses : this.closedClasses));
            }
        }
    }
}
