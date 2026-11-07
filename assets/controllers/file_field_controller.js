import { Controller } from '@hotwired/stimulus';
import { uploadFile, pickFiles } from '../lib/files.js';

/*
 * A field of files (CrudFileType): its files are the items of its list, in their order; what they are is written into
 * its hidden input as JSON - [{ ref, sig, meta }] - which the live form sends with the save. A file chosen or dropped
 * is uploaded at once (to the signed address of the field), one picked in the window of the library is taken as it
 * is. A field of one file has one: a new one takes the place of the old. Items are sorted by dragging them.
 */
export default class extends Controller {
    static targets = ['input', 'list', 'template', 'picker', 'item'];
    static values = { uploadUrl: String, token: String, multiple: Boolean, max: Number };

    choose() {
        this.pickerTarget.click();
    }

    picked() {
        this.uploadAll([...this.pickerTarget.files]);
        this.pickerTarget.value = '';
    }

    async browse() {
        const files = await pickFiles({ token: this.tokenValue });
        for (const file of files) {
            if (!this.has(file.ref)) {
                this.fill(this.add(), file);
            }
        }
        this.sync();
    }

    remove(event) {
        const item = event.currentTarget.closest('[data-file-field-target="item"]');
        item.upload?.abort();
        item.remove();
        this.sync();
    }

    // Drop files from the computer onto the field.
    over(event) {
        if (this.uploadUrlValue && !this.dragged && event.dataTransfer?.types.includes('Files')) {
            event.preventDefault();
            this.element.dataset.over = '';
        }
    }

    leave(event) {
        if (!this.element.contains(event.relatedTarget)) {
            delete this.element.dataset.over;
        }
    }

    drop(event) {
        if (this.dragged || !event.dataTransfer?.files.length) {
            return;
        }
        event.preventDefault();
        delete this.element.dataset.over;
        if (this.uploadUrlValue) {
            this.uploadAll([...event.dataTransfer.files]);
        }
    }

    // Sorting: an item dragged over another one goes before it, or after it past its middle.
    dragStart(event) {
        this.dragged = event.target.closest?.('[data-file-field-target="item"]');
        if (this.dragged) {
            event.dataTransfer.effectAllowed = 'move';
            this.dragged.classList.add('opacity-50');
        }
    }

    dragOver(event) {
        if (!this.dragged) {
            return;
        }
        event.preventDefault();
        const target = event.target.closest('[data-file-field-target="item"]');
        if (!target || target === this.dragged) {
            return;
        }
        const box = target.getBoundingClientRect();
        const style = getComputedStyle(this.listTarget);
        const horizontal = style.display === 'grid' || (style.display === 'flex' && style.flexDirection.startsWith('row'));
        const after = horizontal ? event.clientX > box.left + box.width / 2 : event.clientY > box.top + box.height / 2;
        target.parentNode.insertBefore(this.dragged, after ? target.nextSibling : target);
    }

    dropItem(event) {
        if (this.dragged) {
            event.preventDefault();
        }
    }

    dragEnd() {
        this.dragged?.classList.remove('opacity-50');
        this.dragged = null;
        this.sync();
    }

    uploadAll(files) {
        const room = this.multipleValue
            ? (this.maxValue > 0 ? this.maxValue - this.itemTargets.length : files.length)
            : 1;
        for (const file of files.slice(0, Math.max(0, room))) {
            this.upload(file);
        }
    }

    async upload(file) {
        const item = this.add();
        const progress = item.querySelector('[data-role="progress"]');
        item.dataset.pending = '';
        item.querySelector('[data-role="name"]').textContent = file.name;
        progress.hidden = false;
        const controller = new AbortController();
        item.upload = controller;
        try {
            const stored = await uploadFile(file, this.uploadUrlValue, {
                signal: controller.signal,
                onProgress: (done) => { progress.value = Math.round(done * 100); },
            });
            delete item.dataset.pending;
            this.fill(item, stored);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            delete item.dataset.pending;
            item.dataset.failed = '';
            const message = item.querySelector('[data-role="error"]');
            message.textContent = error.message;
            message.hidden = false;
        } finally {
            progress.hidden = true;
            item.upload = null;
        }
        this.sync();
    }

    // A new item at the end; a field of one file has only this one.
    add() {
        if (!this.multipleValue) {
            for (const item of this.itemTargets) {
                item.upload?.abort();
                item.remove();
            }
        }
        const item = this.templateTarget.content.firstElementChild.cloneNode(true);
        this.listTarget.append(item);

        return item;
    }

    fill(item, file) {
        item.dataset.ref = file.ref;
        item.dataset.sig = file.sig ?? '';
        const image = item.querySelector('[data-role="image"]');
        const icon = item.querySelector('[data-role="icon"]');
        const link = item.querySelector('[data-role="link"]');
        const name = item.querySelector('[data-role="name"]');
        const shown = Boolean(file.image && file.url);
        if (image) {
            // The size "preview" of the image when the panel makes sizes (byfareska_crud.files.images).
            image.src = shown ? (file.preview ?? file.url) : '';
            image.hidden = !shown;
        }
        if (icon) {
            icon.hidden = shown;
        }
        if (link) {
            link.href = file.url ?? '#';
        }
        if (name) {
            name.textContent = file.name;
            name.title = file.name;
        }
        // A file taken in: its alternative text is the name it was uploaded under (made plain by the server) until
        // one is typed.
        const alt = item.querySelector('[data-meta="alt"]');
        if (alt && !alt.value && file.alt) {
            alt.value = file.alt;
        }
    }

    has(ref) {
        return this.itemTargets.some((item) => item.dataset.ref === ref);
    }

    // The files of the list into the hidden input; the live form takes it from there.
    sync() {
        const files = this.itemTargets
            .filter((item) => item.dataset.ref && !('pending' in item.dataset) && !('failed' in item.dataset))
            .map((item) => {
                const file = { ref: item.dataset.ref };
                if (item.dataset.sig) {
                    file.sig = item.dataset.sig;
                }
                const meta = {};
                for (const input of item.querySelectorAll('[data-meta]')) {
                    if (input.value.trim() !== '') {
                        meta[input.dataset.meta] = input.value;
                    }
                }
                if (Object.keys(meta).length) {
                    file.meta = meta;
                }

                return file;
            });
        const value = JSON.stringify(files);
        if (this.inputTarget.value !== value) {
            this.inputTarget.value = value;
            this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
}
