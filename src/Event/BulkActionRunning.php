<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Action\BulkAction;
use Byfareska\Crud\Action\BulkSelection;
use Byfareska\Crud\Resource\CrudFailure;

/**
 * A bulk action is about to be done with the records chosen, once it is offered for them and what its dialog asked
 * for is checked. A listener refuses by throwing a {@see CrudFailure}: nothing is done and its message is shown.
 */
final readonly class BulkActionRunning
{
    public function __construct(
        public BulkAction $action,
        public BulkSelection $selection,
    ) {
    }
}
