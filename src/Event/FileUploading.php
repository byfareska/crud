<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Files\FileRefused;
use Byfareska\Crud\Files\UploadProfile;
use Symfony\Component\HttpFoundation\File\File;

/**
 * A file is about to be written to its storage: it is of a kind and a size the field takes. A listener may refuse it
 * (refuse(): a virus scan, a quota) or change it in place first - strip the EXIF of a photo, shrink it - before its
 * size is read again.
 */
final class FileUploading
{
    public ?FileRefused $refused = null;

    /**
     * @param string $name the name it was uploaded under
     * @param string $mimeType what it is, as read from its bytes
     */
    public function __construct(
        public readonly File $file,
        public readonly UploadProfile $profile,
        public readonly string $name,
        public readonly string $mimeType,
    ) {
    }

    /**
     * @param string $key a translation key, shown to the one who uploaded
     * @param array<string, string|int> $parameters
     */
    public function refuse(string $key, array $parameters = []): void
    {
        $this->refused = new FileRefused($key, $parameters);
    }
}
