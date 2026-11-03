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
 * The link of an {@see EditAction}: the main button of the details page (focused there, keeping the tab in view) and
 * of the preview drawer.
 */
final readonly class EditActionHandler implements RecordActionHandler
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private RecordGuards $guards,
        private RequestStack $requests,
    ) {
    }

    public function button(RecordAction $action, RecordView $record): ?ActionButton
    {
        if (!$action instanceof EditAction) {
            throw new InvalidArgumentException(sprintf(
                '%s makes the buttons of a %s.',
                self::class,
                EditAction::class,
            ));
        }

        $crud = $record->crud;
        if (
            $record->screen === RecordScreen::Form
            || !$crud->can(CrudOperation::Update)
            || !$this->guards->allows(CrudOperation::Update, $record)
        ) {
            return null;
        }

        $parameters = [];
        $attributes = [];
        if ($record->screen === RecordScreen::Details) {
            $parameters['tab'] = $this->requests->getMainRequest()?->query->get('tab');
            $attributes = ['data-tabs-link' => true, 'autofocus' => true];
        }
        $href = $this->urls->generate($crud->route('edit'), $crud->recordParameters($record->id, $parameters));

        return new ActionButton(
            $action->name,
            'crud.action.edit',
            $action->icon,
            'a',
            ['href' => $href] + $attributes,
            $action->inline,
            primary: in_array($record->screen, [RecordScreen::Details, RecordScreen::Preview], true),
            tone: ActionTone::Warning,
        );
    }
}
