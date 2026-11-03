<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Action\BulkAction;
use Byfareska\Crud\Action\BulkResult;
use Byfareska\Crud\Action\BulkSelection;

/**
 * A bulk action was done with the records chosen: what its handler told, for an audit log of who did what to which.
 */
final readonly class BulkActionDone
{
    public function __construct(
        public BulkAction $action,
        public BulkSelection $selection,
        public ?BulkResult $result,
    ) {
    }
}
