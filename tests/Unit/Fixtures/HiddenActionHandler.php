<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Action\ActionButton;
use Byfareska\Crud\Action\RecordAction;
use Byfareska\Crud\Action\RecordActionHandler;
use Byfareska\Crud\Resource\RecordView;

final readonly class HiddenActionHandler implements RecordActionHandler
{
    public function button(RecordAction $action, RecordView $record): ?ActionButton
    {
        return null;
    }
}
