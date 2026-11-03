<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Resource\RecordView;

/**
 * An action a Twig component of the application draws itself, wherever the record has its actions - for what one
 * link or button cannot be: a form, a dropdown of its own, a live component. See {@see RecordAction}.
 *
 * The component gets "record" ({@see RecordView}), "place" ({@see ActionPlace}: a row, the row menu or among the
 * buttons of a page) and "button" ({@see ActionButton}: its text and icon, to look like the others), and the props
 * given here; it declares them (as public properties, or {% props %} of an anonymous component) - objects make no
 * HTML attributes. A component with nothing to offer for a record renders nothing.
 */
final readonly class ComponentAction implements PrioritizedAction
{
    /**
     * @param string $name lower-case; part of the translation key
     * @param string $component name of the Twig component, e.g. "App:RestockButton"
     * @param string $icon a Bootstrap Icons name
     * @param string|null $role only somebody with this role is offered the action
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     * @param list<string> $fields what the component needs of an item of the list besides its columns
     * @param array<string, mixed> $props what else the component gets
     * @param int $priority where it stands among the actions of a record, lower first (PrioritizedAction)
     * @param ActionTone|null $tone the colour of its button; null = Secondary
     */
    public function __construct(
        public string $name,
        public string $component,
        public string $icon,
        public ?string $role = null,
        public bool $inline = false,
        private array $fields = [],
        public array $props = [],
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
        return ComponentActionHandler::class;
    }
}
