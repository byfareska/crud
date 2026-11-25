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
extension comes from the file's bytes, never its name, so a `.php` sent as a JPEG is refused. A kind without an
extension of its own (an empty file, some text formats) is stored as `.bin`, so the web server sends it as a
download instead of no `Content-Type` at all. SVG, HTML, XML and scripts are accepted only when a field names their
MIME type (`accept: ['image/svg+xml']`; `image/*` or `.svg` isn't enough). The same goes for a file whose path ends
in such an extension, whatever its bytes say, and for files picked from the library.

The browser can't choose a storage or a file it wasn't given: upload URLs are signed with their storage and what
they accept, and a submitted reference must be signed by the upload or the library, or already be in the record. A
signed reference must also be of a storage the field picks from and the user may use (`role`), of a kind the field
accepts and no bigger than its `max_size`. A refused value isn't echoed back with names, sizes, URLs or signatures:
the field shows only the files of the record and those signed for it.

A signature covers the storage and the path, without an expiry: a file the user was shown in a field (their upload,
the library, a record they edit) can be attached to any other field over that storage that accepts it.

### Serving uploaded files

A `public` storage under `public/` is served by the web server from the panel's origin. A field that accepts SVG or
HTML there lets anyone who can upload store a script that runs as whoever opens the file. The panel never opens such
a file from its storage's URL: an SVG is shown as an image (`<img>`), and the file is linked only through the signed
download of a `url: signed` storage, which sends it as an attachment. Your own templates should do the same
(`crud_file_link(ref)` is `null` for such a file). To accept SVG or HTML:

- keep it on a `url: signed` storage, or on another origin (`public_url: https://media.example.com`); or
- make the web server send such files from the upload directory as downloads that can't run (an `<img>` still
  shows an SVG):

```nginx
location /uploads/ {
    add_header X-Content-Type-Options nosniff always;
    location ~* \.(svgz?|html?|shtml|xht(ml)?|xml|xslt?|m?js)$ {
        add_header X-Content-Type-Options nosniff always;
        add_header Content-Security-Policy "default-src 'none'; sandbox" always;
        add_header Content-Disposition attachment always;
    }
}
```

```apache
<Directory "/var/www/app/public/uploads">
    Header always set X-Content-Type-Options nosniff
    <FilesMatch "\.(svgz?|html?|shtml|xht(ml)?|xml|xslt?|m?js)$">
        Header always set Content-Security-Policy "default-src 'none'; sandbox"
        Header always set Content-Disposition attachment
    </FilesMatch>
</Directory>
```

No upload ends in `.php` by accident: it gets the extension of its bytes, and scripts are refused unless a field
names their MIME type.

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

In Twig, `crud_file_url(ref)` gives a file's URL, `crud_file(ref)` what the library knows about it, and
`crud_file_link(ref)` the URL to link to from the panel (`null` for SVG or HTML that would run on the panel's origin).

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

With `offload`, the web server sends the file with the headers of its internal location, not those of the panel.
nginx drops `X-Content-Type-Options` and `Content-Security-Policy` of an `X-Accel-Redirect` response, so add them
there:

```nginx
location /_protected/ {
    internal;
    alias /var/www/app/var/uploads/;
    add_header X-Content-Type-Options nosniff always;
    add_header Content-Security-Policy "default-src 'none'; img-src 'self'; sandbox" always;
}
```

| Library | |
| --- | --- |
| `doctrine` (default with ORM) | the `crud_file` table; `files.entity` names an entity of your own |
| `storage` | the storage listed, with a cache |
| `none` | |
| a `FileLibrary` | a DAM, an API |

The library window deletes only what it shows: files its library knows (a `crud_file` row; with `storage`, any file
of the storage except image sizes), of a kind the field accepts, in a storage the user may upload to (not
`upload: false`). A file that records use is deleted only after the panel listed where it's used and the user
confirmed. Anyone who can open a field's library can delete such a file, even one used by records they can't edit.
To prevent that, give the storage a `role`, or keep files in a `FileRemoving` listener (`$event->usages`,
`$event->keep()`). `FileRemover` refuses a file its library doesn't know.

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
