<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Where the web server finds the files of a storage on the disk of the server, to send them in place of PHP: under
 * an internal location of nginx, or in a directory for X-Sendfile.
 */
final readonly class FileOffload
{
    /**
     * @param string $prefix the internal location of nginx ("/_protected/"), or the directory of the storage on the
     *     disk ("/var/app/private") for X-Sendfile
     */
    public function __construct(
        public OffloadHeader $header,
        public string $prefix,
    ) {
    }

    /** What the header says for a file of the storage. */
    public function value(string $path): string
    {
        $prefix = rtrim($this->prefix, '/') . '/';

        return match ($this->header) {
            OffloadHeader::AccelRedirect => $prefix . StorageFileUrls::encodePath($path),
            OffloadHeader::Sendfile => $prefix . $path,
        };
    }
}
