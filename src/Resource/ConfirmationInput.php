<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Symfony\Contracts\Service\ResetInterface;

/**
 * What was typed or chosen in the dialog that asked before the action being done in this request (ConfirmInput): the
 * reason of a delete, the status chosen for the records. Set by the list and the delete button before they act;
 * RecordDeleting and RecordDeleted carry it, and a decorator of RecordDeletion or a bulk action may read it here.
 */
final class ConfirmationInput implements ResetInterface
{
    private ?string $value = null;

    public function set(?string $value): void
    {
        $this->value = $value === null || trim($value) === '' ? null : trim($value);
    }

    public function get(): ?string
    {
        return $this->value;
    }

    public function reset(): void
    {
        $this->value = null;
    }
}
