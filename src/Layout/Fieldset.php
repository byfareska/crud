<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

use LogicException;

/**
 * Fields that belong together, under a heading (EasyAdmin: FormField::addFieldset()). Without a name it is only a
 * line between the fields before and after it.
 */
final readonly class Fieldset implements LayoutGroup
{
    /**
     * @param list<LayoutNode|string> $children rows and fields
     * @param string|null $icon a Bootstrap Icons name, beside the heading
     * @param bool $collapsible the heading folds the fields away; open at first
     * @param bool $collapsed folded away at first (implies collapsible); one holding a field with an error is open
     * @param string|null $class added to the fieldset
     * @param bool $hideEmpty left out of the details when every value of it is null (the server of a mailbox of another
     *     kind)
     * @param string|null $showIf in the form, shown only while the fields say so - the show-if controller's condition,
     *     e.g. "trigger=schedule" or "kind=email; email:filled"
     */
    public function __construct(
        public ?string $name,
        public array $children,
        public ?string $icon = null,
        public bool $collapsible = false,
        public bool $collapsed = false,
        public ?string $class = null,
        public bool $hideEmpty = false,
        public ?string $showIf = null,
    ) {
        if (($collapsible || $collapsed) && $name === null) {
            throw new LogicException('A fieldset that folds needs a name: its heading is what opens it.');
        }
    }

    public function foldable(): bool
    {
        return $this->collapsible || $this->collapsed;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function children(): array
    {
        return $this->children;
    }

    public function with(array $children): static
    {
        return new self(
            $this->name,
            $children,
            $this->icon,
            $this->collapsible,
            $this->collapsed,
            $this->class,
            $this->hideEmpty,
            $this->showIf,
        );
    }
}
