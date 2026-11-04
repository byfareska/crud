<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Event\FileUploaded;
use Byfareska\Crud\Event\FileUploading;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Takes a file into a storage: checks it against the profile of the field, names it (PathGenerator), writes it and
 * has its library keep it.
 *
 * What a file is, is read from its bytes, never from the name it came with: its extension follows from that, so a
 * script named "photo.jpg" is no JPEG, and a "photo.php" that is a JPEG is written as ".jpg". HTML, SVG and scripts are
 * taken only by a field that names their MIME type (UploadProfile::ACTIVE).
 */
final readonly class FileUploader
{
    /** How many times a path taken already is asked for again. */
    private const int ATTEMPTS = 5;

    /**
     * @param int $maxSize in bytes, for a profile that does not say (byfareska_crud.files.max_size)
     */
    public function __construct(
        private FileStorages $storages,
        private FileLibraries $libraries,
        private PathGenerator $paths,
        private ?EventDispatcherInterface $events = null,
        private int $maxSize = 10 * 1024 * 1024,
    ) {
    }

    /**
     * @param string|null $name the name it was uploaded under; null = that of the UploadedFile, else of the file
     *
     * @throws FileRefused not taken: too big, of a kind the profile does not take, refused by a listener
     * @throws FileStorageFailed the storage could not write it
     */
    public function upload(File $file, UploadProfile $profile, ?string $name = null): StoredFile
    {
        if ($file instanceof UploadedFile && !$file->isValid()) {
            throw $file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE
                ? FileRefused::tooBig((int) UploadedFile::getMaxFilesize())
                : FileRefused::notUploaded();
        }
        if (!$this->storages->has($profile->storage) || !$this->storages->settings($profile->storage)->upload) {
            throw FileRefused::storage($profile->storage);
        }
        $name ??= $file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename();

        $max = min($profile->maxSize ?? $this->maxSize, $this->maxSize);
        if ($file->getSize() > $max) {
            throw FileRefused::tooBig($max);
        }
        $mimeType = $this->mimeType($file);
        $extension = $this->extension($mimeType, $name);
        if (!$profile->accepts($mimeType, $extension)) {
            throw FileRefused::kind($mimeType);
        }

        $uploading = new FileUploading($file, $profile, $name, $mimeType);
        $this->events?->dispatch($uploading);
        if ($uploading->refused instanceof FileRefused) {
            throw $uploading->refused;
        }
        // A listener may have changed it (a photo made smaller).
        clearstatcache(true, $file->getPathname());
        $size = (int) filesize($file->getPathname());

        $storage = $this->storages->storage($profile->storage);
        $path = $this->freePath($storage, $profile->storage, $extension, $name);
        $stream = fopen($file->getPathname(), 'rb');
        if ($stream === false) {
            throw new FileStorageFailed(sprintf('The uploaded file "%s" cannot be read.', $name));
        }
        try {
            $storage->write($path, $stream, $mimeType);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        [$width, $height] = $this->dimensions($file, $mimeType);
        $stored = $this->libraries->of($profile->storage)->register(new StoredFile(
            new FileReference($profile->storage, $path),
            $name,
            $mimeType,
            $size,
            $width,
            $height,
            new DateTimeImmutable(),
        ));
        $this->events?->dispatch(new FileUploaded($stored, $profile));

        return $stored;
    }

    private function mimeType(File $file): string
    {
        if (!class_exists(MimeTypes::class)) {
            throw new LogicException('Uploading files needs symfony/mime: composer require symfony/mime.');
        }

        return strtolower(MimeTypes::getDefault()->guessMimeType($file->getPathname()) ?? 'application/octet-stream');
    }

    /**
     * The extension of the kind the file is: the one it came with when the kind has it (".jpeg" stays), else the
     * first of the kind; none for a kind without one.
     */
    private function extension(string $mimeType, string $name): string
    {
        $extensions = MimeTypes::getDefault()->getExtensions($mimeType);
        $given = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($given, $extensions, true) ? $given : ($extensions[0] ?? '');
    }

    private function freePath(FileStorage $storage, string $name, string $extension, string $original): string
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; ++$attempt) {
            $path = $this->paths->generate($name, $extension, $original);
            if (!$storage->exists($path)) {
                return $path;
            }
        }

        throw new FileStorageFailed(sprintf(
            'No free path in the storage "%s" after %d attempts: does its PathGenerator give the same one?',
            $name,
            self::ATTEMPTS,
        ));
    }

    /**
     * @return array{int|null, int|null}
     */
    private function dimensions(File $file, string $mimeType): array
    {
        if (!str_starts_with($mimeType, 'image/') || $mimeType === 'image/svg+xml') {
            return [null, null];
        }
        $size = @getimagesize($file->getPathname());

        return $size === false ? [null, null] : [$size[0], $size[1]];
    }
}
