<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use RuntimeException;

/**
 * A file still used by records is not deleted unless that is confirmed.
 */
final class FileInUse extends RuntimeException
{
    /**
     * @param list<FileUsage> $usages
     */
    public function __construct(
        public readonly FileReference $reference,
        public readonly array $usages,
    ) {
        parent::__construct(sprintf('"%s" is used by %d record(s).', $reference, count($usages)));
    }
}
