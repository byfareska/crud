import { Controller } from '@hotwired/stimulus';
import { closeOverlay, openOverlay, toggleOverlay } from '../lib/overlay.js';

/*
 * A dialog or a drawer of either theme (lib/overlay.js) driven through actions, e.g. closed when a live component
 * says it saved:
 *
 * <dialog data-controller="overlay" data-action="overlay:shown->overlay#focus something:saved@window->overlay#close">
 *     <input data-overlay-target="autofocus">
 *
 * data-overlay-open-value="true": opened as soon as it is on the page (a drawer the address asks for).
 */
export default class extends Controller {
    static targets = ['autofocus'];
    static values = { open: Boolean };

    connect() {
        if (this.openValue) {
            openOverlay(this.element);
        }
    }

    open() {
        openOverlay(this.element);
    }

    close() {
        closeOverlay(this.element);
    }

    toggle() {
        toggleOverlay(this.element);
    }

    // overlay:shown - only its own, not one of a dialog inside.
    focus(event) {
        if (event.target === this.element && this.hasAutofocusTarget) {
            this.autofocusTarget.focus();
        }
    }

    // ← / → between the buttons of the element the action is on: <footer data-action="keydown->overlay#walk">
    walk(event) {
        const step = { ArrowLeft: -1, ArrowRight: 1 }[event.key];
        if (!step || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }
        const buttons = [...event.currentTarget.querySelectorAll('button:not(:disabled), a[href]')];
        const next = buttons[buttons.indexOf(document.activeElement) + step];
        event.preventDefault();
        event.stopPropagation();
        next?.focus();
    }
}
