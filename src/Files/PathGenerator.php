<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * Where in its storage an uploaded file goes. The bundle's is {@see ShardedPathGenerator}; another one (by date,
 * by tenant) is a service named in byfareska_crud.files.path.generator. A path it gives that is taken already is
 * asked for again.
 */
interface PathGenerator
{
    /**
     * @param string $extension of the kind the file is (lower-case, without the dot), "" for none
     * @param string $name the name it was uploaded under
     * @return string a path without a leading "/" nor ".."
     */
    public function generate(string $storage, string $extension, string $name): string;
}
