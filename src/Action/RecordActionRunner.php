<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\RecordView;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Does a {@see RunAction} to a record - a service, autoconfigured, named by the action. It is called in the
 * transaction of RecordChanges, after the guards and RecordChanging, with the record as it is before:
 *
 *     final readonly class ApproveInvoice implements RecordActionRunner
 *     {
 *         public function __construct(private InvoiceRepository $invoices) {}
 *
 *         public function run(RunAction $action, RecordView $record, ?string $input): ?BulkResult
 *         {
 *             $this->invoices->get($record->id)->approve(comment: $input);
 *
 *             return new BulkResult('Zatwierdzono');
 *         }
 *     }
 */
#[AutoconfigureTag(self::TAG)]
interface RecordActionRunner
{
    public const string TAG = 'byfareska_crud.record_action_runner';

    /**
     * @param string|null $input what its dialog asked for (RunAction::$input), checked
     *
     * @return BulkResult|null its toast, or where to go next (redirect); null = the toast crud.toast.done
     *
     * @throws CrudFailure it refuses: nothing is kept, its message is shown
     */
    public function run(RunAction $action, RecordView $record, ?string $input): ?BulkResult;
}
