<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * How the files of a storage are opened in the browser (byfareska_crud.files.storages.<name>.url).
 */
enum UrlMode: string
{
    /** An address anyone can open: the storage's (Flysystem's public_url) or "public_url" of the storage + the path. */
    case Public = 'public';

    /** An address that works for a while ("ttl"), made by the storage itself (S3's presigned). */
    case Temporary = 'temporary';

    /** The panel's own download address, signed and working for "ttl": for a private storage without addresses. */
    case Signed = 'signed';

    /** No address: the files are never shown, only kept. */
    case None = 'none';
}
