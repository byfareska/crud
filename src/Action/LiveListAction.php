<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Component\Crud\CrudList;

/**
 * A button that runs a live action of the list, given the record's id - one of the list of the resource's own
 * (#[AsCrud(listComponent:)], a class extending {@see CrudList}): "toggle active", "move up". Offered where the list
 * is: its rows, the row menu and the preview drawer; not on the pages of the record, which have no list. See
 * {@see RecordAction}.
 *
 *     #[AsCrud(
 *         ...,
 *         listComponent: 'App:ProductList',
 *         actions: [new LiveListAction('toggle', 'toggleActive', 'toggle-on')],
 *     )]
 *
 *     #[LiveAction]
 *     public function toggleActive(#[LiveArg] string $id): void { ... }
 *
 * The live action asks itself who may do it (getDefinition(), the guards): the button is only a way to it.
 */
final readonly class LiveListAction implements PrioritizedAction
{
    /**
     * @param string $name lower-case; part of the translation key crud.<resource>.action.<name>
     * @param string $liveAction the method of the list marked #[LiveAction], taking the argument "id"
     * @param string $icon a Bootstrap Icons name
     * @param string|null $role only somebody with this role is offered the action
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     * @param list<string> $fields what the button needs of an item of the list besides its columns (see
     *     LiveListActionHandler: none; for an action of the application's own that extends it)
     * @param int $priority where it stands among the actions of a record, lower first (PrioritizedAction)
     * @param ActionTone|null $tone the colour of its button; null = Secondary
     */
    public function __construct(
        public string $name,
        public string $liveAction,
        public string $icon,
        public ?string $role = null,
        public bool $inline = false,
        private array $fields = [],
        public int $priority = 0,
        public ?ActionTone $tone = null,
    ) {
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function handler(): string
    {
        return LiveListActionHandler::class;
    }
}
