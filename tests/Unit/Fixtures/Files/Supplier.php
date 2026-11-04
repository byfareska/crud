<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Files;

use Byfareska\Crud\Attribute\CrudFile;

/**
 * A record with files in columns: a logo (a reference), a gallery (a list of them with "alt"), attachments.
 */
final class Supplier
{
    #[CrudFile(accept: ['images'])]
    public ?string $logo = null;

    /** @var list<array{ref: string, alt?: string}> */
    #[CrudFile(accept: ['images'], meta: ['alt'])]
    public array $gallery = [];

    /** @var list<string> */
    public array $attachments = [];

    public string $name = '';
}
