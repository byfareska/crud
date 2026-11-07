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
 * It speaks the language of the page (<html lang>) when the pack of that language is loaded - the packs of TinyMCE 8
 * of tinymce-i18n, imported after TinyMCE: `import 'tinymce-i18n/langs8/pl.js'`; "pt-BR" takes "pt-BR", "fr" takes
 * "fr-FR". Without the pack it is English (and says so in the console); "language" in the options chooses another.
 *
 * With files (the field's "files"), an image pasted, dropped or uploaded in the dialog goes to the field's storage, and
 * the dialogs of an image and of a link pick from the window of the library. When the field offers sizes of images, a
 * chosen image has a toolbar with a menu of its sizes.
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
            const language = languageOf(tinymce);
            const config = {
                ...DEFAULTS,
                ...(language ? { language } : {}),
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
                    if (files?.sizes) {
                        offerSizes(editor, files.sizes, changed);
                    }
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
                    .then((file) => files.addressOf?.(file) ?? file.url)
                    .catch((error) => Promise.reject({ message: error.message, remove: true }));
            }
            if (files?.pick) {
                config.file_picker_types = 'file image';
                config.file_picker_callback = (callback, value, meta) => {
                    files.pick({ images: meta.filetype === 'image' }).then(([file]) => {
                        if (file?.url) {
                            const image = meta.filetype === 'image';
                            callback(
                                image ? files.addressOf?.(file) ?? file.url : file.url,
                                image ? { alt: file.alt ?? '' } : { text: file.name },
                            );
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

/*
 * A toolbar over a chosen image with the menu of its sizes (files.sizes of lib/rich_text.js): a size puts its address
 * and pixels on the image.
 */
function offerSizes(editor, sizes, changed) {
    const imageOf = (node) => (node?.nodeName === 'IMG' ? node : null);
    editor.ui.registry.addMenuButton('crudimagesize', {
        text: sizes.label,
        fetch: (callback) => {
            const image = imageOf(editor.selection.getNode());
            const choices = (image && sizes.of(image.getAttribute('src'))) || [];
            callback(choices.map((choice) => ({
                type: 'togglemenuitem',
                text: choice.label,
                active: choice.current,
                onAction: () => {
                    editor.undoManager.transact(() => {
                        editor.dom.setAttribs(image, {
                            src: choice.url,
                            'data-mce-src': choice.url,
                            width: choice.width ?? null,
                            height: choice.height ?? null,
                        });
                    });
                    editor.nodeChanged();
                    changed(editor.getContent());
                },
            })));
        },
    });
    editor.ui.registry.addContextToolbar('crudimagesize', {
        predicate: (node) => Boolean(imageOf(node) && sizes.of(node.getAttribute('src'))),
        items: 'crudimagesize',
        position: 'node',
        scope: 'node',
    });
}

// The colours of the panel: data-color-scheme of the theme controller, or a theme of Bootstrap or daisyUI.
function isDark() {
    const root = document.documentElement;

    return root.getAttribute('data-color-scheme') === 'dark'
        || ['data-bs-theme', 'data-theme'].some((name) => root.getAttribute(name) === 'dark');
}

/*
 * The pack loaded (tinymce.addI18n) of the language of the page: the same code, else one of the same language ("fr" -
 * "fr-FR"); undefined for English, or without one.
 */
export function languageOf(tinymce, lang = document.documentElement.lang) {
    const plain = (code) => code.replace('_', '-').toLowerCase();
    const wanted = plain(lang ?? '');
    if (wanted === '' || wanted.split('-')[0] === 'en') {
        return undefined;
    }
    const codes = Object.keys(tinymce.util?.I18n?.getData?.() ?? {});
    const code = codes.find((candidate) => plain(candidate) === wanted)
        ?? codes.find((candidate) => plain(candidate).split('-')[0] === wanted.split('-')[0]);
    if (code === undefined && !warned.has(wanted)) {
        warned.add(wanted);
        console.warn(`@byfareska/crud: no language pack of TinyMCE for "${lang}" - import it after TinyMCE, e.g. `
            + `import 'tinymce-i18n/langs8/${wanted.split('-')[0]}.js'.`);
    }

    return code;
}

const warned = new Set();
