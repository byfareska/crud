/*
 * localStorage access that never throws: storage can be unavailable (private mode, blocked
 * site data) or hold something unexpected, and the panel must work without it.
 */

export function readString(key, fallback = null) {
    try {
        return localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
}

export function writeString(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Nothing to do: the value simply will not be remembered.
    }
}

export function readJson(key, fallback) {
    try {
        const raw = localStorage.getItem(key);

        return raw === null ? fallback : JSON.parse(raw);
    } catch {
        return fallback;
    }
}

export function writeJson(key, value) {
    writeString(key, JSON.stringify(value));
}
