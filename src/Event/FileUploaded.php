<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Files\StoredFile;
use Byfareska\Crud\Files\UploadProfile;

/**
 * A file was written to its storage and kept by its library - not yet used by any record (that is when a record
 * with it is saved).
 */
final readonly class FileUploaded
{
    public function __construct(
        public StoredFile $file,
        public UploadProfile $profile,
    ) {
    }
}
