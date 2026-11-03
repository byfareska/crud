<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\RecordView;
use InvalidArgumentException;

/**
 * The button of a {@see ComponentAction}: its component, given the record.
 */
final readonly class ComponentActionHandler implements RecordActionHandler
{
    public function button(RecordAction $action, RecordView $record): ActionButton
    {
        if (!$action instanceof ComponentAction) {
            throw new InvalidArgumentException(sprintf(
                '%s makes the buttons of a %s.',
                self::class,
                ComponentAction::class,
            ));
        }

        return new ActionButton(
            $action->name,
            $record->crud->key('action.' . $action->name),
            $action->icon,
            inline: $action->inline,
            component: $action->component,
            props: ['record' => $record] + $action->props,
            tone: $action->tone,
        );
    }
}
