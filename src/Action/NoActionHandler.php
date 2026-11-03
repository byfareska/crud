<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\RecordView;

/**
 * The handler of {@see NoAction}: no button for any record.
 */
final readonly class NoActionHandler implements RecordActionHandler
{
    public function button(RecordAction $action, RecordView $record): ?ActionButton
    {
        return null;
    }
}
