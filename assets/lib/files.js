/*
 * Files of the panel (byfareska_crud.files), for a field of files and for an editor of rich text:
 *
 * - uploadFile(file, url, { onProgress, signal }) uploads a File (or a Blob) to the signed address of a field
 *   (data-file-field-upload-url-value) and resolves with what the server kept of it:
 *   { ref, sig, url, name, mime, size, width, height, image } - or rejects with an Error whose message says why;
 * - pickFiles({ token }) opens the window of the library of the page with the token of a field
 *   (data-file-field-token-value) and resolves with the files chosen, [] when it is closed without.
 */

// The window of the library, in the layout of the panel.
export const LIBRARY_ID = 'crud-file-library';

export function uploadFile(file, url, { onProgress = null, signal = null } = {}) {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('POST', url);
        // What tells the server the upload comes from a script of the page (no other site can send it unasked).
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');
        request.responseType = 'json';
        if (onProgress) {
            request.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    onProgress(event.loaded / event.total);
                }
            });
        }
        request.addEventListener('load', () => {
            const body = request.response ?? {};
            if (request.status >= 200 && request.status < 300 && body.ref) {
                resolve(body);
            } else {
                reject(new Error(body.error ?? body.detail ?? request.statusText ?? 'Upload failed'));
            }
        });
        request.addEventListener('error', () => reject(new Error(request.statusText || 'Upload failed')));
        request.addEventListener('abort', () => reject(new DOMException('The upload was cancelled.', 'AbortError')));
        signal?.addEventListener('abort', () => request.abort());

        const data = new FormData();
        data.append('file', file, file.name ?? 'file');
        request.send(data);
    });
}

export function pickFiles({ token }) {
    const library = document.getElementById(LIBRARY_ID);
    if (!library) {
        return Promise.reject(new Error(`@byfareska/crud: no #${LIBRARY_ID} on the page (byfareska_crud.files).`));
    }

    return new Promise((resolve) => {
        library.dispatchEvent(new CustomEvent('file-library:open', { detail: { token, resolve } }));
    });
}
