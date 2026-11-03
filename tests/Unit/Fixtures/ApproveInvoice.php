<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Action\BulkResult;
use Byfareska\Crud\Action\RecordActionRunner;
use Byfareska\Crud\Action\RunAction;
use Byfareska\Crud\Resource\RecordView;

/**
 * Does a RunAction, telling which record it did it to and with what.
 */
final class ApproveInvoice implements RecordActionRunner
{
    /** @var list<array{string, ?string}> */
    public array $done = [];

    public function run(RunAction $action, RecordView $record, ?string $input): BulkResult
    {
        $this->done[] = [$record->id, $input];

        return new BulkResult('approved ' . $record->id);
    }
}
