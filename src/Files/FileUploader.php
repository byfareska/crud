<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Byfareska\Crud\Event\FileUploaded;
use Byfareska\Crud\Event\FileUploading;
use Byfareska\Crud\Files\Image\ImageVariants;
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
 *
 * The path the PathGenerator gives is checked before anything is written there: a generator of the application may
 * make it of what the browser sent (the name of the upload, a value of the record), and a path of "..", of a hidden
 * file (".htaccess"), of another extension than the kind's (".html" for a text) or among the sizes of images would put
 * a file of the browser's choosing where the storage - or the web server serving it - takes it for something else.
 */
final readonly class FileUploader
{
    /**
     * The extension of a file of a kind without one of its own: a web server sends it as bytes to download
     * (application/octet-stream) - without any, it would send no Content-Type and a browser would guess one from the
     * bytes, HTML among them.
     */
    public const string NEUTRAL_EXTENSION = 'bin';

    /** The longest path a PathGenerator may give, in bytes: what the library of the bundle keeps (crud_file.path). */
    public const int MAX_PATH_LENGTH = 255;

    /** How many times a path taken already is asked for again. */
    private const int ATTEMPTS = 5;

    /**
     * @param int $maxSize in bytes, for a profile that does not say (byfareska_crud.files.max_size)
     * @param ImageVariants|null $variants the sizes of images, whose directory no upload goes into; null = none
     */
    public function __construct(
        private FileStorages $storages,
        private FileLibraries $libraries,
        private PathGenerator $paths,
        private ?EventDispatcherInterface $events = null,
        private int $maxSize = 10 * 1024 * 1024,
        private ?ImageVariants $variants = null,
    ) {
    }

    /**
     * @param string|null $name the name it was uploaded under; null = that of the UploadedFile, else of the file
     *
     * @throws FileRefused not taken: too big, of a kind the profile does not take, refused by a listener
     * @throws FileStorageFailed the storage could not write it, or the PathGenerator gave a path no upload may have
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
     * first of the kind; NEUTRAL_EXTENSION for a kind without one.
     */
    private function extension(string $mimeType, string $name): string
    {
        $extensions = MimeTypes::getDefault()->getExtensions($mimeType);
        $given = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($given, $extensions, true) ? $given : ($extensions[0] ?? self::NEUTRAL_EXTENSION);
    }

    private function freePath(FileStorage $storage, string $name, string $extension, string $original): string
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; ++$attempt) {
            $path = $this->paths->generate($name, $extension, $original);
            $this->assertMayWrite($name, $path, $extension);
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
     * A path the PathGenerator may give: that of a reference (FileReference: no "..", no leading "/", no backslash, no
     * control character), in UTF-8, of at most MAX_PATH_LENGTH bytes; no part of it empty or starting with "." - no
     * hidden file (".htaccess", ".user.ini"), nothing a storage writes at another path ("a//b", "a/./b" are "a/b");
     * ending in the extension of the kind the file is; not among the sizes of images, where it would be taken for a
     * size of another image.
     *
     * @throws FileStorageFailed it may not
     */
    private function assertMayWrite(string $storage, string $path, string $extension): void
    {
        $reference = FileReference::tryParse($storage . '://' . $path);
        $variants = $this->variants;
        $wrong = match (true) {
            !$reference instanceof FileReference || preg_match('//u', $path) !== 1 => 'no path of a file in a storage',
            strlen($path) > self::MAX_PATH_LENGTH => sprintf('longer than %d bytes', self::MAX_PATH_LENGTH),
            array_any(
                explode('/', $path),
                static fn (string $part): bool => $part === '' || str_starts_with($part, '.'),
            ) => 'a path with an empty part or one starting with "."',
            $reference->extension() !== strtolower($extension) => sprintf(
                'not of the extension of the kind of the file (".%s")',
                $extension,
            ),
            $variants instanceof ImageVariants && $variants->isVariant($reference) => sprintf(
                'in the directory of the sizes of images ("%s")',
                $variants->directory(),
            ),
            default => null,
        };
        if ($wrong !== null) {
            throw new FileStorageFailed(sprintf(
                'The PathGenerator gave "%s" for an upload to the storage "%s": %s.',
                $path,
                $storage,
                $wrong,
            ));
        }
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
