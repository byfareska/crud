import { Controller } from '@hotwired/stimulus';
import { readString, writeString } from '../lib/storage.js';

// Same key as the inline script of @CrudTheme/panel/base.html.twig, which applies the saved theme before first paint.
const STORAGE_KEY = 'panel-theme';
const DARK = '(prefers-color-scheme: dark)';

/*
 * Switches between the dark and light theme and remembers the choice.
 *
 * <button data-controller="theme" data-action="theme#toggle theme:toggle@window->theme#toggle">
 *
 * theme#set with data-theme-theme-param="light", "dark" or "auto" picks one; "auto" follows the colour scheme of the
 * system (prefers-color-scheme), also when it changes, and forgets the saved choice - which is what the inline script
 * does when nothing is saved. data-theme-auto-value="true": while nothing is saved, the page follows the system as it
 * changes from the start (without it, only once "auto" was picked). data-theme-storage-key-value: another key to remember it under (the inline script reads
 * "panel-theme"). Every change is told with a "theme:change" event on window (detail: theme - "light" or "dark",
 * mode - "light", "dark" or "auto") - a listener may save it in the profile of the user.
 *
 * More schemes than light and dark - a high contrast, a "dim" one of a tenant: data-theme-modes-value='["light",
 * "dark", "dim"]' (toggle goes through them in turn, set takes any of them) and data-theme-dark-value='["dark", "dim"]'
 * (the ones that are dark: data-color-scheme="dark" on <html>, which the charts go by). Bootstrap draws a scheme of
 * its own by [data-bs-theme="dim"] of the stylesheet of the application. data-theme-initial-value: the scheme the user
 * chose (of the profile, from the server) - taken while the browser has none saved.
 *
 * A picker of many schemes (the "daisyui" theme: its colour themes) marks the one in use: every option target with
 * data-theme-theme-param gets aria-checked="true" when it is the mode in use ("auto" while nothing is saved), else
 * "false".
 */
export default class extends Controller {
    static targets = ['option'];

    static values = {
        storageKey: { type: String, default: STORAGE_KEY },
        auto: Boolean,
        modes: { type: Array, default: ['light', 'dark'] },
        dark: { type: Array, default: ['dark'] },
        initial: String,
    };

    connect() {
        this.follow = this.follow.bind(this);
        this.media = window.matchMedia?.(DARK) ?? null;
        this.media?.addEventListener('change', this.follow);
        // What the profile says, while the browser has nothing saved of its own.
        if (this.initialValue && this.modesValue.includes(this.initialValue) && !readString(this.storageKeyValue)) {
            this.apply(this.initialValue, this.initialValue);
        }
        this.mark(this.mode());
    }

    optionTargetConnected(option) {
        this.markOne(option, this.mode());
    }

    disconnect() {
        this.media?.removeEventListener('change', this.follow);
    }

    // The next of the schemes, in turn: light and dark, or those of data-theme-modes-value.
    toggle() {
        const modes = this.modesValue.length > 0 ? this.modesValue : ['light', 'dark'];
        const root = document.documentElement;
        const current = modes.indexOf(root.getAttribute('data-theme') ?? root.getAttribute('data-bs-theme'));
        const next = modes[(current + 1) % modes.length];

        writeString(this.storageKeyValue, next);
        this.apply(next, next);
    }

    // theme#set - data-theme-theme-param="light" / "dark" / "auto".
    set({ params: { theme } = {} }) {
        if (theme === 'auto') {
            this.auto = true;
            this.forget();
            this.apply(this.system(), 'auto');
        } else if (this.modesValue.includes(theme)) {
            writeString(this.storageKeyValue, theme);
            this.apply(theme, theme);
        }
    }

    // The system changed its colour scheme: followed in the "auto" mode, while nothing is saved.
    follow() {
        if ((this.autoValue || this.auto) && !readString(this.storageKeyValue)) {
            this.apply(this.system(), 'auto');
        }
    }

    system() {
        return this.media?.matches ? 'dark' : 'light';
    }

    // What is in use: the saved scheme, else "auto" - the system's.
    mode() {
        const saved = readString(this.storageKeyValue);

        return saved && this.modesValue.includes(saved) ? saved : 'auto';
    }

    mark(mode) {
        this.optionTargets.forEach((option) => this.markOne(option, mode));
    }

    markOne(option, mode) {
        option.setAttribute('aria-checked', String(option.dataset.themeThemeParam === mode));
    }

    forget() {
        try {
            localStorage.removeItem(this.storageKeyValue);
        } catch {
            // Nothing to do: storage is not there to forget anything.
        }
    }

    apply(theme, mode) {
        const root = document.documentElement;

        // data-bs-theme for Bootstrap, data-theme for anything else (a Tailwind dark: variant), data-color-scheme
        // whether it is dark at all - of a scheme of its own too.
        root.setAttribute('data-bs-theme', theme);
        root.setAttribute('data-theme', theme);
        const scheme = this.darkValue.includes(theme) ? 'dark' : 'light';
        root.setAttribute('data-color-scheme', scheme);
        root.style.colorScheme = scheme;
        this.mark(mode);
        this.dispatch('change', { prefix: 'theme', target: window, detail: { theme, mode } });
    }
}
