<?php

declare(strict_types=1);

namespace Byfareska\Crud\Page;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\MapCrud;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\RecordView;

/**
 * The resource a page is of and, on a page of a record, the record - opened by {@see CrudPages}, which checked that
 * the one asking may see it. The pages of the bundle have one, and so has any controller of the application that takes
 * it with {@see MapCrud}; the panel goes by it ({@see CurrentCrud}): the item of the menu lit, the panel of the page.
 *
 * @phpstan-import-type RecordDetails from CrudRecords
 */
final readonly class CrudContext
{
    /** The request attribute it is kept in. */
    public const string ATTRIBUTE = '_crud_context';

    /**
     * @param string|null $id of the record; null on a page of the whole resource (the list, the page to add one)
     * @param RecordDetails|null $details what the details page shows of the record (CrudRecords::details()); null
     *     without a record, or when the resource cannot read one
     * @param RecordView|null $record the record as its guards allowed it and its actions get it; null without a record
     */
    public function __construct(
        public AsCrud $crud,
        public ?string $id = null,
        public ?array $details = null,
        public ?RecordView $record = null,
    ) {
    }

    /** What the record is named by; null without a record or a name to go by. */
    public function title(): ?string
    {
        return $this->details['title'] ?? null;
    }
}
