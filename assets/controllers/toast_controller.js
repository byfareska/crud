import { Controller } from '@hotwired/stimulus';

const VARIANTS = ['success', 'danger', 'warning', 'info'];

/*
 * Toasts for short status messages. Anything can show one by dispatching a browser event:
 *
 *   window.dispatchEvent(new CustomEvent('toast:show', { detail: { title, description, variant } }))
 *   $this->dispatchBrowserEvent('toast:show', ['title' => ..., 'description' => ..., 'variant' => 'danger']) // live component
 *
 * variant is "success" (the default), "danger", "warning" or "info": the classes of each are in the variants value
 * ({"danger": {"toast": "…", "icon": "…"}}), laid on the parts of the toast that ask for them (data-toast-part).
 *
 * With a <template data-toast-target="template"> inside, every event shows a toast of its own, stacked over the ones
 * still shown (at most data-toast-max-value of them, 5; the oldest goes first). Without one (the markup of old), the
 * element is the one toast and a new message takes the place of the last one.
 *
 * The toasts are data-turbo-permanent: a Turbo visit moves them into the new <body>, which disconnects and connects the
 * controller again - so the time each is to hide at is kept on its element and the countdown goes on after the move.
 */
export default class extends Controller {
    static targets = ['title', 'description', 'template'];
    static values = {
        duration: { type: Number, default: 3200 },
        max: { type: Number, default: 5 },
        variants: Object,
    };

    connect() {
        this.timers = new Map();
        if (this.hasTemplateTarget) {
            for (const toast of this.element.querySelectorAll('[data-toast-item]')) {
                this.hideAt(toast, Number(toast.dataset.hideAt) || Date.now());
            }
        } else if (this.element.classList.contains('show')) {
            this.hideAt(this.element, Number(this.element.dataset.hideAt) || Date.now());
        }
    }

    disconnect() {
        for (const timer of this.timers.values()) {
            clearTimeout(timer);
        }
        this.timers.clear();
    }

    show({ detail = {} }) {
        const variant = VARIANTS.includes(detail.variant) ? detail.variant : 'success';

        if (!this.hasTemplateTarget) {
            this.titleTarget.textContent = detail.title ?? '';
            this.descriptionTarget.textContent = detail.description ?? '';
            this.descriptionTarget.hidden = !detail.description;
            this.element.dataset.variant = variant;

            this.element.classList.add('show');
            this.element.hidden = false;
            this.hideAt(this.element, Date.now() + this.durationValue);

            return;
        }

        const toast = this.templateTarget.content.firstElementChild.cloneNode(true);
        toast.dataset.toastItem = '';
        toast.dataset.variant = variant;
        const part = (name) => toast.querySelector(`[data-toast-part="${name}"]`);
        part('title').textContent = detail.title ?? '';
        part('description').textContent = detail.description ?? '';
        part('description').hidden = !detail.description;
        for (const element of [toast, ...toast.querySelectorAll('[data-toast-part]')]) {
            const name = element === toast ? 'toast' : element.dataset.toastPart;
            const classes = this.variantsValue[variant]?.[name];
            if (classes) {
                element.classList.add(...classes.split(/\s+/).filter(Boolean));
            }
        }

        this.element.append(toast);
        const shown = [...this.element.querySelectorAll('[data-toast-item]')];
        for (const old of shown.slice(0, Math.max(0, shown.length - Math.max(1, this.maxValue)))) {
            this.remove(old);
        }
        this.hideAt(toast, Date.now() + this.durationValue);
    }

    hideAt(toast, time) {
        toast.dataset.hideAt = String(time);
        clearTimeout(this.timers.get(toast));
        this.timers.set(toast, setTimeout(() => {
            if (toast === this.element) {
                this.timers.delete(toast);
                this.element.classList.remove('show');
                this.element.hidden = true;
            } else {
                this.remove(toast);
            }
        }, Math.max(0, time - Date.now())));
    }

    remove(toast) {
        clearTimeout(this.timers.get(toast));
        this.timers.delete(toast);
        toast.remove();
    }
}
