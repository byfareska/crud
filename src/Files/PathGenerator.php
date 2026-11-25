<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Where in its storage an uploaded file goes. The bundle's is {@see ShardedPathGenerator}; another one (by date,
 * by tenant) is a service named in byfareska_crud.files.path.generator. A path it gives that is taken already is
 * asked for again: an upload does not replace a file that is there.
 *
 * What the browser sent (the name of the upload) is the browser's: a part of a path made of it is made plain first
 * (ShardedPathGenerator::slug()). FileUploader writes nothing at a path that is not one of a reference
 * (FileReference), that is longer than FileUploader::MAX_PATH_LENGTH, has an empty part or one starting with "."
 * (".htaccess"), ends in another extension than the one given, or is in the directory of the sizes of images: the
 * upload fails (FileStorageFailed).
 */
interface PathGenerator
{
    /**
     * @param string $extension of the kind the file is (lower-case, without the dot): FileUploader::NEUTRAL_EXTENSION
     *     for a kind without one
     * @param string $name the name it was uploaded under
     * @return string a path without a leading "/" nor "..", ending in "." and the extension
     */
    public function generate(string $storage, string $extension, string $name): string;
}
