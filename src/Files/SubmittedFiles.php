<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The files the fields of the form being saved hold now, by the path of the field ("gallery", "section.field"):
 * written by the fields of files and rich text as the form is submitted, read by FileUsageListener once the record is
 * saved - one form is saved by a request.
 */
final class SubmittedFiles implements ResetInterface
{
    /** @var array<string, list<FileReference>> */
    private array $fields = [];

    /**
     * @param list<FileReference> $references
     */
    public function record(string $field, array $references): void
    {
        $this->fields[$field] = $references;
    }

    /**
     * What the fields held, forgotten.
     *
     * @return array<string, list<FileReference>>
     */
    public function take(): array
    {
        $fields = $this->fields;
        $this->fields = [];

        return $fields;
    }

    public function reset(): void
    {
        $this->fields = [];
    }
}
