<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordView;

/**
 * A record was changed otherwise than by its form (see RecordChanging) - still in the transaction of the change, so
 * what a listener writes (an audit log) is kept with it or not at all.
 */
final readonly class RecordChanged
{
    /**
     * @param string $action what was done: the name of the action
     * @param RecordView $before what the record was
     * @param string|null $input what was typed or chosen in the dialog that asked (ConfirmInput)
     */
    public function __construct(
        public AsCrud $crud,
        public string $id,
        public string $action,
        public RecordView $before,
        public ?string $input = null,
    ) {
    }
}
