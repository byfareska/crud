# Files

File fields, galleries, a library for picking uploaded files again, rich text with images, and image sizes. They're
off until configured: without `byfareska_crud.files` there is no service, route, form type or table for them.

```sh
composer require symfony/mime league/flysystem
```

```yaml
byfareska_crud:
    files:
        storages:
            # served by the web server: <img src="/uploads/ab/cd/x7k2....jpg">
            public: { service: app.public_storage, public_url: /uploads }
            # outside the web root: opened through the panel, signed for "ttl" seconds
            private: { service: app.private_storage, url: signed, role: ROLE_ADMIN }
```

```yaml
# config/services.yaml (or the storages of league/flysystem-bundle)
services:
    app.public_storage:
        class: League\Flysystem\Filesystem
        arguments: [!service { class: League\Flysystem\Local\LocalFilesystemAdapter, arguments: ['%kernel.project_dir%/public/uploads'] }]
    app.private_storage:
        class: League\Flysystem\Filesystem
        arguments: [!service { class: League\Flysystem\Local\LocalFilesystemAdapter, arguments: ['%kernel.project_dir%/var/uploads'] }]
```

With Doctrine ORM, the bundle keeps two tables: `crud_file` (the library) and `crud_file_usage` (where each file is
used). Run `doctrine:migrations:diff` once files are enabled.

## References

A file is stored as a reference, its storage plus its path: `public://ab/cd/x7k2....jpg`. The reference can live in a
column, a JSON list, a file entity or a gallery item. The storage name is part of every reference, so renaming a
storage is a data migration. A reference starting with `http(s)://` is an external file: it is shown but never
uploaded to.

Uploads get random names in random directories (`path: { levels: 2, width: 2, length: 32 }`; `levels: 0` for S3). The
extension comes from the file's bytes, never its name, so a `.php` sent as a JPEG is refused. SVG and HTML are
accepted only when a field names their MIME type.

The browser can't choose a storage or a file it wasn't given: upload URLs are signed with their storage and what
they accept, and a submitted reference must be signed by the upload or the library, or already be in the record.

## File fields

```php
#[ORM\Column(nullable: true)]
#[CrudFile(accept: ['images'])]
public ?string $logo = null;           // "public://ab/cd/....png"

/** @var list<string> */
#[CrudFile(storage: 'private', options: ['max_files' => 5])]
public array $attachments = [];        // a JSON list of references
```

In a form type of your own, use `CrudFileType`. Its options:

- `storage`, `multiple`;
- `accept` (`images`, `image/*`, `application/pdf`, `.pdf`);
- `max_size`, `max_files`;
- `meta` (`['alt', 'caption']`);
- `browse`, `library`, `gallery`.

`binding` decides how the record holds the files:

| Binding | |
| --- | --- |
| `column` (default) | a reference or a list of them (with `meta`: `[{"ref": "...", "alt": "..."}]`) |
| `items` | an entity per file with its position and meta (`item_class`, `item_file`, `item_position`, `item_meta`, `item_factory`): a Doctrine gallery |
| `record` | a relation to a file entity (`record_class`), or ManyToMany with `multiple` |
| a `FileBinding` | your own |

In Twig, `crud_file_url(ref)` gives a file's URL and `crud_file(ref)` what the library knows about it.

## Rich text

`CrudRichTextType` (or `#[CrudRichText]` on a property) is an HTML textarea with an editor on top. The bundle ships a
TinyMCE adapter; TinyMCE itself is your application's:

```js
import { registerRichTextEditor } from '@byfareska/crud';
import { tinymceEditor } from '@byfareska/crud/editors/tinymce.js';

registerRichTextEditor('tinymce', tinymceEditor(() => import('./tinymce.js').then((m) => m.default)));
```

```yaml
byfareska_crud:
    rich_text:
        editor: tinymce
        options: { license_key: gpl }
        files: false
```

With `files`, images pasted or dropped into the editor are uploaded, and the image and link dialogs pick from the
library. This needs a storage with lasting public URLs.

The submitted HTML is what the browser sent. **Sanitize it where it is shown** (`sanitize_html` from
symfony/html-sanitizer).

Another editor is an object with `mount(textarea, { options, files, changed })` that returns `{ destroy() }` (see
`assets/lib/rich_text.js`).

## Storages and libraries

| Storage option | |
| --- | --- |
| `url` | `public` (the storage's URL, or `public_url` + path), `temporary` (works for `ttl` seconds, e.g. S3), `signed` (the panel's download URL), `none` |
| `offload` | the web server sends signed files: `{ header: X-Accel-Redirect, prefix: /_protected/ }` |
| `role`, `upload`, `library`, `urls`, `variants` | |

| Library | |
| --- | --- |
| `doctrine` (default with ORM) | the `crud_file` table; `files.entity` names an entity of your own |
| `storage` | the storage listed, with a cache |
| `none` | |
| a `FileLibrary` | a DAM, an API |

To support another source, implement `FileStorage`, `FileLibrary`, `FileRecordMapper` or `PathGenerator`. The
`Byfareska\Crud\Files\Testing\*ContractTestCase` classes test them. Events: `FileUploading`, `FileUploaded`,
`FileRemoving`.

## Usages and pruning

```sh
bin/console byfareska:crud:files:prune            # lists unused files older than prune_after (PT24H)
bin/console byfareska:crud:files:prune --force    # deletes them
bin/console byfareska:crud:files:reindex          # rebuilds the usage index (--resource=product)
```

The index only knows about saves made through the panel. Run `reindex` after imports or API writes.

## Image sizes

```sh
composer require intervention/image
```

```yaml
byfareska_crud:
    files:
        images:
            resizer: auto          # imagick | gd | an ImageResizer service id
            variants:
                large: { width: 1600, format: webp }
                medium: { width: 800 }
                # thumbnail (96x96, cover) and preview (480x480) are built in
```

Prefer Imagick: it keeps the colour profile, converts CMYK and reads HEIC.

- A size is a box (`width`, `height`) with a fit: `inside` (default) or `cover`.
- An image is never enlarged.
- `format` and `quality` (default 82) apply to lossy formats.
- Metadata (including location) is removed.

A storage's `variants` setting decides when sizes are made:

| `variants` | |
| --- | --- |
| `eager` (default) | when the image is uploaded; run `variants:generate` for older images |
| `lazy` | when a size is first requested, through the panel |
| `none` | no sizes |

```sh
bin/console byfareska:crud:files:variants:generate [--storage=] [--variant=] [--overwrite]
bin/console byfareska:crud:files:variants:clear [--unconfigured --force]
```

Lists show `thumbnail`; the details, file fields and the library show `preview`; in Twig use
`crud_file_url(ref, 'large')`. A rich text field offers sizes with
`'files' => ['accept' => ['images'], 'sizes' => ['large', 'medium'], 'size' => 'large']`.
