<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\RecordView;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The link of a {@see RouteAction}.
 */
final readonly class RouteActionHandler implements RecordActionHandler
{
    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function button(RecordAction $action, RecordView $record): ActionButton
    {
        if (!$action instanceof RouteAction) {
            throw new InvalidArgumentException(sprintf(
                '%s makes the buttons of a %s.',
                self::class,
                RouteAction::class,
            ));
        }

        return new ActionButton(
            $action->name,
            $action->label ?? $record->crud->key('action.' . $action->name),
            $action->icon,
            'a',
            array_filter([
                'href' => $this->urls->generate(
                    $action->route,
                    $action->parameters($record->crud, $record->id, $record->item),
                ),
                'data-turbo' => $action->turbo ? null : 'false',
                'target' => $action->target,
                // A page of another tab gets nothing of this one.
                'rel' => $action->target === '_blank' ? 'noopener' : null,
            ], static fn (?string $value): bool => $value !== null),
            $action->inline,
            tone: $action->tone,
        );
    }
}
