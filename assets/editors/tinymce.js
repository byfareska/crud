/*
 * The adapter of TinyMCE (7 or later) for the fields of rich text (CrudRichTextType). TinyMCE is the application's -
 * installed (npm i tinymce, with the models, themes, icons, skins and plugins it loads: see its guide to bundling) or
 * from a CDN (window.tinymce):
 *
 *     import tinymce from 'tinymce';
 *     import { registerRichTextEditor } from '@byfareska/crud';
 *     import { tinymceEditor } from '@byfareska/crud/editors/tinymce.js';
 *
 *     registerRichTextEditor('tinymce', tinymceEditor(tinymce, { license_key: 'gpl' }));
 *
 * Or loaded only on a page with a field of rich text - a function giving it:
 *
 *     registerRichTextEditor('tinymce', tinymceEditor(() => import('./tinymce.js').then((module) => module.default)));
 *
 * Its options are DEFAULTS, then those given here, then byfareska_crud.rich_text.options, then the field's
 * "editor_options". TinyMCE asks for a licence: license_key "gpl" or the key bought.
 *
 * With files (the field's "files"), an image pasted, dropped or uploaded in the dialog goes to the field's storage, and
 * the dialogs of an image and of a link pick from the window of the library.
 */
export const DEFAULTS = {
    menubar: false,
    branding: false,
    promotion: false,
    plugins: 'autolink lists link image table code',
    toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | code',
    // Addresses of files as the storage gives them ("/uploads/ab/cd/x.jpg"): the server reads the files back from them.
    relative_urls: false,
    remove_script_host: true,
    convert_urls: true,
};

export function tinymceEditor(tinymce = globalThis.tinymce, defaults = {}) {
    return {
        async mount(textarea, { options = {}, files = null, changed }) {
            if (typeof tinymce === 'function') {
                tinymce = await tinymce();
            }
            if (!tinymce?.init) {
                throw new Error('@byfareska/crud: no TinyMCE - give it to tinymceEditor(tinymce).');
            }
            const dark = isDark();
            const config = {
                ...DEFAULTS,
                skin: dark ? 'oxide-dark' : 'oxide',
                content_css: dark ? 'dark' : 'default',
                ...defaults,
                ...options,
                target: textarea,
                setup(editor) {
                    // Not the HTML it starts with, as TinyMCE writes it: that is no change of the user's.
                    let ready = false;
                    editor.on('init', () => {
                        ready = true;
                    });
                    // SetContent: also an image uploaded, its address put in place of the blob.
                    editor.on('input change undo redo ExecCommand SetContent', () => {
                        if (ready) {
                            changed(editor.getContent());
                        }
                    });
                    defaults.setup?.(editor);
                },
            };
            if (files?.upload) {
                config.automatic_uploads = true;
                config.images_upload_handler = (blobInfo, progress) => files
                    .upload(blobInfo.blob(), {
                        name: blobInfo.filename(),
                        onProgress: (share) => progress(Math.round(share * 100)),
                    })
                    .then((file) => file.url)
                    .catch((error) => Promise.reject({ message: error.message, remove: true }));
            }
            if (files?.pick) {
                config.file_picker_types = 'file image';
                config.file_picker_callback = (callback, value, meta) => {
                    files.pick({ images: meta.filetype === 'image' }).then(([file]) => {
                        if (file?.url) {
                            callback(file.url, meta.filetype === 'image' ? { alt: '' } : { text: file.name });
                        }
                    });
                };
            }
            const [editor] = await tinymce.init(config);

            return {
                destroy() {
                    editor?.remove();
                },
            };
        },
    };
}

// The colours of the panel: data-color-scheme of the theme controller, or a theme of Bootstrap or daisyUI.
function isDark() {
    const root = document.documentElement;

    return root.getAttribute('data-color-scheme') === 'dark'
        || ['data-bs-theme', 'data-theme'].some((name) => root.getAttribute(name) === 'dark');
}
