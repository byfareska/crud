<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Twig\ActionButtonRenderer;

/**
 * An action of one record, ready to be drawn ({@see ActionButtonRenderer}, crud_action()): the theme draws it as fits
 * the place - a button with its icon on a row of the list, an item of the row menu, a button with its text on a page
 * or in the preview drawer - unless it has a component of its own, which draws it itself.
 */
final readonly class ActionButton
{
    /** The colour of the button: the one asked for, else Danger for an action that destroys, else Secondary. */
    public ActionTone $tone;

    /**
     * @param string $label a translation key
     * @param string $icon a Bootstrap Icons name
     * @param 'a'|'button' $tag
     * @param array<string, string|int|bool|null> $attributes of the element, escaped by the theme (crud_attrs())
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     * @param bool $danger it destroys something: drawn in red
     * @param bool $primary the main thing to do where it is (editing, on the details page): drawn as such, last
     * @param string|null $component a Twig component drawing it instead of the theme; it gets the props below and
     *     "button" (this) and "place" (ActionPlace)
     * @param array<string, mixed> $props of the component
     * @param string|null $resource the resource of the record, by name: its theme and its blocks draw the button
     *     first (crud_<resource>_action_<name>_<place>) - set by RecordActions
     * @param ActionTone|null $tone the colour of the button, by what it does (see $tone)
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $icon,
        public string $tag = 'button',
        public array $attributes = [],
        public bool $inline = false,
        public bool $danger = false,
        public bool $primary = false,
        public ?string $component = null,
        public array $props = [],
        public ?string $resource = null,
        ?ActionTone $tone = null,
    ) {
        $this->tone = $tone ?? ($danger ? ActionTone::Danger : ActionTone::Secondary);
    }

    /** The same button, of a record of that resource (see $resource). */
    public function of(string $resource): self
    {
        return $this->resource === $resource ? $this : new self(
            $this->name,
            $this->label,
            $this->icon,
            $this->tag,
            $this->attributes,
            $this->inline,
            $this->danger,
            $this->primary,
            $this->component,
            $this->props,
            $resource,
            $this->tone,
        );
    }

    /**
     * The same button, disabled and saying why ("A paid invoice is not deleted."): what a guard refused and explained
     * (ExplainsRefusal) - nothing it would do is left on it.
     */
    public function refused(string $reason): self
    {
        return new self(
            $this->name,
            $this->label,
            $this->icon,
            'button',
            ['type' => 'button', 'disabled' => true, 'aria-disabled' => 'true', 'title' => $reason],
            $this->inline,
            $this->danger,
            $this->primary,
            resource: $this->resource,
            tone: $this->tone,
        );
    }
}
