<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\RecordView;

/**
 * A RecordActionHandler whose one action is several buttons, depending on the record - the transitions of a
 * Symfony Workflow it may take now ("send", "approve", "reject"), the documents of an order to download:
 *
 *     public function buttons(RecordAction $action, RecordView $record): iterable
 *     {
 *         foreach ($this->workflow->getEnabledTransitions($this->orders->get($record->id)) as $transition) {
 *             yield new ActionButton($transition->getName(), 'app.order.' . $transition->getName(), 'arrow-right',
 *                 'a', ['href' => $this->urls->generate('app_order_transition', [...])]);
 *         }
 *     }
 *
 * Asked in place of button(); each is drawn like any other, in the order given.
 */
interface MakesButtons
{
    /**
     * @return iterable<ActionButton>
     */
    public function buttons(RecordAction $action, RecordView $record): iterable;
}
