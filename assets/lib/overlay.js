/*
 * Dialogs, drawers and menus of any theme, opened and closed the same way by the controllers:
 *
 * - the "bootstrap5" theme draws Bootstrap's own (.modal, .offcanvas, .dropdown-menu); the application hands its
 *   Bootstrap over once - the bundle does not import it, so a theme without Bootstrap does not need it:
 *
 *       import { Modal, Offcanvas, Dropdown } from 'bootstrap';
 *       useBootstrap({ Modal, Offcanvas, Dropdown });
 *
 * - any other theme ("tailwind") draws native elements: <dialog> for a dialog or a drawer (opened by a button with
 *   commandfor="id" command="show-modal", or openOverlay()) and <details data-dropdown> for a menu.
 *
 * In any theme, data-overlay-open="<id>" on an element opens that dialog or drawer when it is clicked.
 *
 * Either way the element tells what happens to it with the events overlay:show, overlay:shown and overlay:hidden
 * (they bubble, like Bootstrap's), and overlay:hide before it is closed: that one can be cancelled
 * (event.preventDefault() - a form with unsaved changes), and the element then stays open. Bootstrap's own ways of
 * closing (Esc, the backdrop, data-bs-dismiss) ask too, and so do Esc and a click outside a <dialog>.
 *
 * Another kind of dialog or drawer (of a library of the application's) is plugged in with an adapter, asked before
 * the built-in ones:
 *
 *     registerOverlayAdapter({
 *         matches: (element) => element.matches('.my-drawer'),
 *         open: (element) => …,
 *         close: (element) => …,
 *         isOpen: (element) => …,
 *     });
 *
 * openOverlay(), closeOverlay(), toggleOverlay() (and so the controllers) then go through it; overlay:hide is
 * dispatched before close() is called, the other events are the adapter's to dispatch.
 */
let bootstrap = null;
const adapters = [];

// What is open now: a dialog, a drawer or a menu.
export const OPEN = '.modal.show, .offcanvas.show, .dropdown-menu.show, dialog[open], details[data-dropdown][open]';
// What is a dialog or a drawer, open or not: the keys of the page do not reach into a closed one.
export const OVERLAY = '.modal, .offcanvas, dialog';

export function useBootstrap({ Modal, Offcanvas, Dropdown }) {
    const first = bootstrap === null;
    bootstrap = { Modal, Offcanvas, Dropdown };
    if (!first) {
        return;
    }
    for (const kind of ['modal', 'offcanvas']) {
        for (const [from, to] of [['show', 'overlay:show'], ['shown', 'overlay:shown'], ['hidden', 'overlay:hidden']]) {
            document.addEventListener(`${from}.bs.${kind}`, (event) => emit(event.target, to));
        }
        // Bootstrap's hide event can be cancelled too: whatever closes the element asks first.
        document.addEventListener(`hide.bs.${kind}`, (event) => {
            if (!emit(event.target, 'overlay:hide', true)) {
                event.preventDefault();
            }
        });
    }
}

// The first adapter whose matches() says yes drives the element; the last one registered is asked first.
export function registerOverlayAdapter(adapter) {
    for (const method of ['matches', 'open', 'close', 'isOpen']) {
        if (typeof adapter?.[method] !== 'function') {
            throw new TypeError(`@byfareska/crud: an overlay adapter needs a ${method}(element) method.`);
        }
    }
    adapters.unshift(adapter);
}

function adapterOf(element) {
    return adapters.find((adapter) => adapter.matches(element)) ?? null;
}

export function anyOverlayOpen() {
    return document.querySelector(OPEN) !== null;
}

export function isOpen(element) {
    const adapter = adapterOf(element);
    if (adapter) {
        return Boolean(adapter.isOpen(element));
    }

    return element instanceof HTMLDialogElement ? element.open : element.classList.contains('show');
}

export function openOverlay(element) {
    if (!element) {
        return;
    }
    const adapter = adapterOf(element);
    if (adapter) {
        adapter.open(element);

        return;
    }
    if (element instanceof HTMLDialogElement) {
        if (!element.open) {
            emit(element, 'overlay:show');
            element.showModal();
            shown(element);
        }

        return;
    }
    component(element)?.getOrCreateInstance(element).show();
}

export function closeOverlay(element) {
    if (!element) {
        return;
    }
    const adapter = adapterOf(element);
    if (adapter) {
        if (emit(element, 'overlay:hide', true)) {
            adapter.close(element);
        }

        return;
    }
    if (element instanceof HTMLDialogElement) {
        closeDialog(element);

        return;
    }
    // Bootstrap asks overlay:hide itself (hide.bs.*, see useBootstrap).
    component(element)?.getInstance(element)?.hide();
}

// A <dialog> closed unless overlay:hide was cancelled.
function closeDialog(dialog) {
    if (dialog.open && emit(dialog, 'overlay:hide', true)) {
        dialog.close();
    }
}

export function toggleOverlay(element) {
    if (element && isOpen(element)) {
        closeOverlay(element);
    } else {
        openOverlay(element);
    }
}

// Every open menu closed - e.g. before a dialog is opened from an item of one.
export function closeMenus() {
    for (const menu of document.querySelectorAll('.dropdown-menu.show')) {
        const toggle = menu.parentElement?.querySelector('[data-bs-toggle="dropdown"]');
        if (toggle) {
            bootstrap?.Dropdown.getInstance(toggle)?.hide();
        }
    }
    for (const details of document.querySelectorAll('details[data-dropdown][open]')) {
        details.open = false;
    }
}

function component(element) {
    if (!bootstrap) {
        console.warn('@byfareska/crud: a Bootstrap dialog or drawer, but no useBootstrap({ Modal, Offcanvas, Dropdown }) was called.');

        return null;
    }

    return element.classList.contains('modal') ? bootstrap.Modal : bootstrap.Offcanvas;
}

// false when a listener cancelled it (only a cancelable event can be).
function emit(element, name, cancelable = false) {
    return element.dispatchEvent(new CustomEvent(name, { bubbles: true, cancelable }));
}

// A <dialog> says once that it is shown, whichever way it was opened.
const announced = new WeakSet();

function shown(dialog) {
    if (dialog.open && !announced.has(dialog)) {
        announced.add(dialog);
        emit(dialog, 'overlay:shown');
    }
}

// What the native elements do not do on their own (once per page).
if (typeof document !== 'undefined') {
    // "close" does not bubble, but goes past document on its way down.
    document.addEventListener('close', (event) => {
        if (event.target instanceof HTMLDialogElement) {
            announced.delete(event.target);
            emit(event.target, 'overlay:hidden');
        }
    }, true);

    // Esc, closedby="any" and command="request-close" go through "cancel", which can be cancelled: overlay:hide is asked.
    document.addEventListener('cancel', (event) => {
        if (event.target instanceof HTMLDialogElement && !emit(event.target, 'overlay:hide', true)) {
            event.preventDefault();
        }
    }, true);

    // A button with commandfor / command="show-modal" (invoker commands): native where the browser has them.
    if ('commandForElement' in HTMLButtonElement.prototype) {
        document.addEventListener('command', (event) => {
            if (event.target instanceof HTMLDialogElement && event.command === 'show-modal' && !event.target.open) {
                emit(event.target, 'overlay:show');
                setTimeout(() => shown(event.target));
            } else if (event.target instanceof HTMLDialogElement && event.command === 'close' && event.target.open
                && !emit(event.target, 'overlay:hide', true)) {
                event.preventDefault();
            }
        }, true);
    } else {
        document.addEventListener('click', (event) => {
            const button = event.target instanceof Element ? event.target.closest('button[commandfor]') : null;
            const dialog = button ? document.getElementById(button.getAttribute('commandfor')) : null;
            if (!(dialog instanceof HTMLDialogElement)) {
                return;
            }
            const command = button.getAttribute('command');
            if (command === 'show-modal') {
                openOverlay(dialog);
            } else if (command === 'close' || command === 'request-close') {
                closeDialog(dialog);
            }
        });
    }

    // data-overlay-open="<id>" on anything (a cell of a table): a click opens that dialog or drawer, in any theme.
    document.addEventListener('click', (event) => {
        const opener = event.target instanceof Element ? event.target.closest('[data-overlay-open]') : null;
        if (opener) {
            openOverlay(document.getElementById(opener.dataset.overlayOpen));
        }
    });

    // closedby="any": a click outside the dialog closes it, also where the browser does not know the attribute.
    if (!('closedBy' in HTMLDialogElement.prototype)) {
        document.addEventListener('click', (event) => {
            const dialog = event.target;
            if (!(dialog instanceof HTMLDialogElement) || !dialog.open || dialog.getAttribute('closedby') !== 'any') {
                return;
            }
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) {
                closeDialog(dialog);
            }
        });
    }

    // Turbo is about to keep a copy of the page: without what is open, so "back" does not bring back a dead backdrop.
    document.addEventListener('turbo:before-cache', () => {
        for (const dialog of document.querySelectorAll('dialog[open]')) {
            dialog.close();
        }
        for (const details of document.querySelectorAll('details[data-dropdown][open]')) {
            details.open = false;
        }
        for (const element of document.querySelectorAll('.modal.show, .offcanvas.show')) {
            component(element)?.getInstance(element)?.dispose();
            element.classList.remove('show', 'showing', 'hiding');
            element.removeAttribute('aria-modal');
            element.removeAttribute('role');
            if (element.classList.contains('modal')) {
                element.style.display = 'none';
            }
        }
        // Bootstrap's own, put into <body> - not the backdrop inside a daisyUI <dialog class="modal">.
        for (const backdrop of document.querySelectorAll('body > .modal-backdrop, body > .offcanvas-backdrop')) {
            backdrop.remove();
        }
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    });
}
