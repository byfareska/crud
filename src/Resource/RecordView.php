<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\UseCase\Page;

/**
 * One record as a page has it: an item of the list, or what the details page and the preview drawer show of it.
 *
 * In the list it comes with the page of the list it is on, for what depends on the other records: its neighbours, or
 * data about every record of the page read at once rather than row by row (kept by the page, e.g. in a WeakMap).
 */
final readonly class RecordView
{
    /**
     * @param string $id as in a URL
     * @param array<string, mixed> $item its values by field: the columns of the list (and what else it asks, see
     *     AsCrud::$fields, RecordAction::fields(), RecordGuard::fields()), or every property of the record; empty on
     *     the edit form of a resource that cannot read one
     * @param RecordScreen $screen where it is shown
     * @param Page|null $list the page of the list the record is on, with the other records and the paging; null
     *     outside the list (the preview drawer, the details page, the edit form)
     */
    public function __construct(
        public AsCrud $crud,
        public string $id,
        public array $item,
        public RecordScreen $screen,
        public ?Page $list = null,
    ) {
    }
}
