/*
 * The editors of fields of rich text (CrudRichTextType), by the name the field says (data-rich-text-editor-value,
 * byfareska_crud.rich_text.editor). An editor is an object with one function:
 *
 *     mount(textarea, { options, files, changed }) → { destroy() }
 *
 * - textarea: the field's own, with the HTML; the editor draws itself over it;
 * - options: what the field and byfareska_crud.rich_text.options give the editor;
 * - files: null when the field uploads nothing, else
 *   { upload(blob, { name, onProgress }) → Promise<file>, pick({ images }) → Promise<file[]> | null }
 *   - a file is { ref, url, name, alt, mime, size, width, height, image } (see lib/files.js); pick is null without a
 *     library;
 * - changed(html): to be called with the HTML whenever it changes - it goes into the textarea and the live form.
 *
 * The bundle's adapter of TinyMCE is editors/tinymce.js:
 *
 *     import tinymce from 'tinymce';
 *     import { registerRichTextEditor } from '@byfareska/crud';
 *     import { tinymceEditor } from '@byfareska/crud/editors/tinymce.js';
 *
 *     registerRichTextEditor('tinymce', tinymceEditor(tinymce));
 */
const editors = new Map();
const waiting = new Map();

export function registerRichTextEditor(name, editor) {
    if (typeof editor?.mount !== 'function') {
        throw new TypeError(`@byfareska/crud: the editor "${name}" has no mount(textarea, context).`);
    }
    editors.set(name, editor);
    for (const resolve of waiting.get(name) ?? []) {
        resolve(editor);
    }
    waiting.delete(name);
}

// The editor of a name, when it is registered: an application may register it after the controllers connect.
export function richTextEditor(name) {
    if (editors.has(name)) {
        return Promise.resolve(editors.get(name));
    }

    return new Promise((resolve) => {
        waiting.set(name, [...(waiting.get(name) ?? []), resolve]);
    });
}
