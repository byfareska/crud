<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * The header that hands the sending of a signed file to the web server
 * (byfareska_crud.files.storages.<name>.offload.header): PHP checks the address, the server sends the bytes.
 */
enum OffloadHeader: string
{
    /** nginx: an internal location ("location /_protected/ { internal; alias /var/app/private/; }"). */
    case AccelRedirect = 'X-Accel-Redirect';

    /** Apache (mod_xsendfile), lighttpd: the file on the disk. */
    case Sendfile = 'X-Sendfile';
}
