<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Action\RecordActionHandler;
use Byfareska\Crud\Action\RecordAction;

/** An action of a kind of the application's own, never offered: see HiddenActionHandler. */
final readonly class HiddenAction implements RecordAction
{
    /**
     * @param class-string<RecordActionHandler> $handler
     */
    public function __construct(
        public string $name,
        public ?string $role = null,
        public bool $inline = false,
        private string $handler = HiddenActionHandler::class,
    ) {
    }

    public function fields(): array
    {
        return ['secret', 'id'];
    }

    public function handler(): string
    {
        return $this->handler;
    }
}
