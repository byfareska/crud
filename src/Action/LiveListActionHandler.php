<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use InvalidArgumentException;

/**
 * The button of a {@see LiveListAction}: the live action of the list, with the record's id - only where the list is
 * (its rows and the preview drawer).
 */
final readonly class LiveListActionHandler implements RecordActionHandler
{
    public function button(RecordAction $action, RecordView $record): ?ActionButton
    {
        if (!$action instanceof LiveListAction) {
            throw new InvalidArgumentException(sprintf(
                '%s makes the buttons of a %s.',
                self::class,
                LiveListAction::class,
            ));
        }

        if (!in_array($record->screen, [RecordScreen::List, RecordScreen::Preview], true)) {
            return null;
        }

        return new ActionButton(
            $action->name,
            $record->crud->key('action.' . $action->name),
            $action->icon,
            'button',
            [
                'type' => 'button',
                'data-action' => 'live#action',
                'data-live-action-param' => $action->liveAction,
                'data-live-id-param' => $record->id,
            ],
            $action->inline,
            tone: $action->tone,
        );
    }
}
