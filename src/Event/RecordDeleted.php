<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\RecordView;

/**
 * A record was deleted - from the list, its details page, its edit form or several at once (dispatched on Symfony's
 * event dispatcher after the driver deleted it, still in the transaction of the delete:
 * {@see \Byfareska\Crud\Resource\RecordTransaction}).
 */
final readonly class RecordDeleted
{
    /**
     * @param RecordView|null $record what it was: every property, when the resource can read one (as RecordDeleting had
     *     it)
     * @param string|null $input what was typed or chosen in the dialog that asked (a reason: ConfirmInput)
     */
    public function __construct(
        public AsCrud $crud,
        public string $id,
        public ?RecordView $record = null,
        public ?string $input = null,
    ) {
    }
}
