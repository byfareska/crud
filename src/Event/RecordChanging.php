<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\RecordView;

/**
 * A record is about to be changed otherwise than by its form - an action of it (RunAction), a bulk action, a change
 * of the application going through RecordChanges - after its guards allowed it, in the transaction of the change. A
 * listener refuses by throwing a {@see CrudFailure}: nothing is changed and its message is shown.
 */
final readonly class RecordChanging
{
    /**
     * @param RecordView $record as it is before
     * @param string $action what is done: the name of the action
     * @param string|null $input what was typed or chosen in the dialog that asked (ConfirmInput)
     */
    public function __construct(
        public RecordView $record,
        public string $action,
        public ?string $input = null,
    ) {
    }
}
