<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Driver\CopiesRecords;
use Byfareska\Crud\Driver\CrudDrivers;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\RecordGuards;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The link of a {@see DuplicateAction}: the add form with ?from=<id>.
 */
final readonly class DuplicateActionHandler implements RecordActionHandler
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private RecordGuards $guards,
        private CrudDrivers $drivers,
    ) {
    }

    public function button(RecordAction $action, RecordView $record): ?ActionButton
    {
        if (!$action instanceof DuplicateAction) {
            throw new InvalidArgumentException(sprintf(
                '%s makes the buttons of a %s.',
                self::class,
                DuplicateAction::class,
            ));
        }

        $crud = $record->crud;
        if (
            $record->screen === RecordScreen::Form
            || !$crud->can(CrudOperation::Create)
            || !$crud->can(CrudOperation::Read)
            || !$this->drivers->of($crud) instanceof CopiesRecords
            || !$this->guards->allows(CrudOperation::Read, $record)
        ) {
            return null;
        }

        return new ActionButton(
            $action->name,
            'crud.action.duplicate',
            $action->icon,
            'a',
            ['href' => $this->urls->generate(
                $crud->route('new'),
                $crud->recordParameters(null, ['from' => $record->id]),
            )],
            $action->inline,
            tone: ActionTone::Success,
        );
    }
}
