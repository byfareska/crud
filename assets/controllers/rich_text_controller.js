import { Controller } from '@hotwired/stimulus';
import { uploadFile, pickFiles } from '../lib/files.js';
import { richTextEditor } from '../lib/rich_text.js';

/*
 * A field of rich text (CrudRichTextType): the editor registered under its name (lib/rich_text.js) drawn over its
 * textarea. What is typed goes back into the textarea, with an "input" event the live form sends. The element is
 * data-live-ignore: a render of the form does not take the editor away.
 */
export default class extends Controller {
    static targets = ['input'];
    static values = {
        editor: String,
        options: Object,
        uploadUrl: String,
        token: String,
        imageToken: String,
        sizes: Object,
    };

    async connect() {
        this.connected = true;
        const editor = await richTextEditor(this.editorValue);
        if (!this.connected || this.instance) {
            return;
        }
        const options = Array.isArray(this.optionsValue) ? {} : this.optionsValue;
        this.instance = await editor.mount(this.inputTarget, {
            options,
            files: this.files(),
            changed: (html) => this.changed(html),
        });
        if (!this.connected) {
            this.destroy();
        }
    }

    disconnect() {
        this.connected = false;
        this.destroy();
    }

    destroy() {
        this.instance?.destroy();
        this.instance = null;
    }

    changed(html) {
        if (this.inputTarget.value === html) {
            return;
        }
        this.inputTarget.value = html;
        this.inputTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    files() {
        if (!this.uploadUrlValue && !this.tokenValue) {
            return null;
        }
        const pick = ({ images = false } = {}) => {
            const token = images ? this.imageTokenValue : this.tokenValue;

            return token ? pickFiles({ token }) : Promise.resolve([]);
        };

        const sizes = this.sizes();

        return {
            upload: (blob, { name = null, onProgress = null } = {}) => {
                if (!this.uploadUrlValue) {
                    return Promise.reject(new Error('This field uploads no files.'));
                }
                const file = blob instanceof File || !name ? blob : new File([blob], name, { type: blob.type });

                return uploadFile(file, this.uploadUrlValue, { onProgress });
            },
            pick: this.tokenValue || this.imageTokenValue ? pick : null,
            addressOf: (file) => sizes?.take(file) ?? file.url,
            sizes: sizes ? { label: sizes.label, of: sizes.of } : null,
        };
    }

    /*
     * The sizes of images the field offers (data-rich-text-sizes-value: RichTextFiles::view()): those of the images of
     * its HTML, and of each file uploaded or picked since - known by any of their addresses.
     */
    sizes() {
        const sizes = this.hasSizesValue && !Array.isArray(this.sizesValue) ? this.sizesValue : null;
        if (!sizes?.choices?.length) {
            return null;
        }
        const known = new Map();
        const remember = (image) => {
            for (const address of [image.url, ...Object.values(image.sizes ?? {}).map((size) => size.url)]) {
                if (address) {
                    known.set(absolute(address), image);
                }
            }
        };
        (sizes.images ?? []).forEach(remember);

        return {
            label: sizes.label,
            // A file put into the text: remembered, and its address in the size it is put in with.
            take(file) {
                const image = { url: file.url, width: file.width, height: file.height, sizes: file.sizes ?? {} };
                remember(image);

                return (sizes.initial ? image.sizes[sizes.initial]?.url : null) ?? file.url;
            },
            // The sizes of the image at an address, the image itself first; null when it has none to choose.
            of(address) {
                const image = address ? known.get(absolute(address)) : undefined;
                if (!image) {
                    return null;
                }
                const current = absolute(address);
                const choices = [
                    { name: null, label: sizes.original, url: image.url, width: image.width, height: image.height },
                    ...sizes.choices
                        .filter((choice) => image.sizes?.[choice.name]?.url)
                        .map((choice) => ({ name: choice.name, label: choice.label, ...image.sizes[choice.name] })),
                ];

                return choices.length < 2
                    ? null
                    : choices.map((choice) => ({ ...choice, current: absolute(choice.url) === current }));
            },
        };
    }
}

// An address as the browser resolves it: "/uploads/a.jpg" and "https://host/uploads/a.jpg" are the same file.
function absolute(address) {
    try {
        return new URL(address, document.baseURI).href;
    } catch {
        return address;
    }
}
