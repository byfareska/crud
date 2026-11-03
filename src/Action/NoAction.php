<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * No button: declared in "actions" by the name of a built-in one, it takes its place - `new NoAction('details')` leaves
 * the details out of the actions of a record while the resource still reads it (its page, the preview drawer).
 */
final readonly class NoAction implements RecordAction
{
    public ?string $role;

    public bool $inline;

    public function __construct(
        public string $name,
    ) {
        $this->role = null;
        $this->inline = false;
    }

    public function fields(): array
    {
        return [];
    }

    public function handler(): string
    {
        return NoActionHandler::class;
    }
}
