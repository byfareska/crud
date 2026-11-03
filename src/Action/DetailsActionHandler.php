<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The link of a {@see DetailsAction}. From the edit form it keeps the tab in view (?tab=..., data-tabs-link).
 */
final readonly class DetailsActionHandler implements RecordActionHandler
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private RecordGuards $guards,
        private RequestStack $requests,
    ) {
    }

    public function button(RecordAction $action, RecordView $record): ?ActionButton
    {
        if (!$action instanceof DetailsAction) {
            throw new InvalidArgumentException(sprintf(
                '%s makes the buttons of a %s.',
                self::class,
                DetailsAction::class,
            ));
        }

        $crud = $record->crud;
        if (
            $record->screen === RecordScreen::Details
            || !$crud->can(CrudOperation::Read)
            || !$this->guards->allows(CrudOperation::Read, $record)
        ) {
            return null;
        }

        $parameters = [];
        $attributes = [];
        if ($record->screen === RecordScreen::Form) {
            $parameters['tab'] = $this->requests->getMainRequest()?->query->get('tab');
            $attributes['data-tabs-link'] = true;
        }
        $href = $this->urls->generate($crud->route('show'), $crud->recordParameters($record->id, $parameters));

        return new ActionButton(
            $action->name,
            'crud.action.details',
            $action->icon,
            'a',
            ['href' => $href] + $attributes,
            $action->inline,
            tone: ActionTone::Info,
        );
    }
}
