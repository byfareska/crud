<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * A tab of a page (EasyAdmin: FormField::addTab()). Tabs are the outermost parts of a layout and, when there is
 * one, everything at the top is a tab. A page left with a single tab shows its content without the tab bar.
 */
final readonly class Tab implements LayoutGroup
{
    /**
     * @param string|null $icon a Bootstrap Icons name
     * @param list<LayoutNode|string> $children columns, fieldsets, rows and fields
     * @param string|null $badge a property of the record shown beside the heading (e.g. a count); nothing when it is
     *     null, false or empty, 0 is shown
     * @param string|null $class added to the pane
     * @param string|null $showIf in the form, shown only while the fields say so (its tab button with it) - the
     *     show-if controller's condition, e.g. "kind=company"
     */
    public function __construct(
        public string $name,
        public ?string $icon = null,
        public array $children = [],
        public ?string $badge = null,
        public ?string $class = null,
        public ?string $showIf = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function children(): array
    {
        return $this->children;
    }

    public function with(array $children): static
    {
        return new self($this->name, $this->icon, $children, $this->badge, $this->class, $this->showIf);
    }
}
