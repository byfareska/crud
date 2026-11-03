<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * Fields side by side (EasyAdmin: FormField::addRow() and setColumns()). Each takes an equal part of the width,
 * unless it is a Field with "cols"; below the breakpoint they are one under another.
 */
final readonly class Row implements LayoutGroup
{
    /**
     * @param list<LayoutNode|string> $children fields only (Field or a name; CrudLayout::validate() sees to it)
     * @param Breakpoint $breakpoint from which width of the screen the fields stand side by side; Xs = always
     */
    public function __construct(
        public array $children,
        public Breakpoint $breakpoint = Breakpoint::Md,
    ) {
    }

    /** The width of a field of the row; null = an equal share. */
    public function spanOf(Field $field): ?Span
    {
        return is_int($field->cols) ? Span::from($this->breakpoint, $field->cols) : $field->cols;
    }

    public function name(): null
    {
        return null;
    }

    public function children(): array
    {
        return $this->children;
    }

    public function with(array $children): static
    {
        return new self($children, $this->breakpoint);
    }
}
