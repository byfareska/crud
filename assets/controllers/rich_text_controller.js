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
    static values = { editor: String, options: Object, uploadUrl: String, token: String, imageToken: String };

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

        return {
            upload: (blob, { name = null, onProgress = null } = {}) => {
                if (!this.uploadUrlValue) {
                    return Promise.reject(new Error('This field uploads no files.'));
                }
                const file = blob instanceof File || !name ? blob : new File([blob], name, { type: blob.type });

                return uploadFile(file, this.uploadUrlValue, { onProgress });
            },
            pick: this.tokenValue || this.imageTokenValue ? pick : null,
        };
    }
}
