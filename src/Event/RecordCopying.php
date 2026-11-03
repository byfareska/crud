<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordView;

/**
 * An add form starts as a copy of a record (?from=<id>, DuplicateAction): the driver copied what the record has for the
 * fields of the form (CopiesRecords); a listener copies more - the lines of an order, the variants of a product, a
 * name with "(copy)" - into $data, the new entity or DTO of the form. Nothing is saved until the form is.
 */
final readonly class RecordCopying
{
    /**
     * @param RecordView $source the record copied, every property when the resource can read one
     * @param mixed $data what the add form is filled into: the new entity, the DTO of the add form
     */
    public function __construct(
        public AsCrud $crud,
        public RecordView $source,
        public mixed $data,
    ) {
    }
}
