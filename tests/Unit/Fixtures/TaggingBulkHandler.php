<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\BulkAction;
use Byfareska\Crud\Action\BulkActionHandler;
use Byfareska\Crud\Action\BulkResult;
use Byfareska\Crud\Action\BulkSelection;

/**
 * Offered for any records but the 13th; tells which ones it did.
 */
final readonly class TaggingBulkHandler implements BulkActionHandler
{
    public function button(BulkAction $action, BulkSelection $selection): ?ActionButton
    {
        return in_array('13', $selection->ids, true) ? null : new ActionButton($action->name, $action->name, 'tag');
    }

    public function run(BulkAction $action, BulkSelection $selection): BulkResult
    {
        return new BulkResult(sprintf('%s: %s', $action->name, implode(', ', $selection->ids)));
    }
}
