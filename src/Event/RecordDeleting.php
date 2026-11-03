<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Resource\CrudFailure;
use Byfareska\Crud\Resource\RecordView;

/**
 * A record is about to be deleted - from the list, its details page, its edit form or several at once - after its
 * guards allowed it (dispatched on Symfony's event dispatcher). A listener refuses by throwing a {@see CrudFailure}:
 * nothing is deleted and its message is shown, e.g. new CrudFailure(409, translationKey: 'app.invoice.paid').
 */
final readonly class RecordDeleting
{
    /**
     * @param RecordView $record every property, when the resource can read one
     * @param string|null $input what was typed or chosen in the dialog that asked (a reason: ConfirmInput)
     */
    public function __construct(
        public RecordView $record,
        public ?string $input = null,
    ) {
    }
}
