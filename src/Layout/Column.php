<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * A column of the grid (EasyAdmin: FormField::addColumn()). Columns that follow each other stand side by side from
 * the "md" breakpoint up; a column left alone on a page takes the whole width.
 */
final readonly class Column implements LayoutGroup
{
    public const Breakpoint BREAKPOINT = Breakpoint::Md;

    /**
     * @param int|Span|null $cols 12ths of the width from "md" up, or a Span for other breakpoints; null = an equal
     *     share with the others
     * @param list<LayoutNode|string> $children fieldsets, rows and fields
     * @param string|null $name gives the column a heading
     * @param string|null $icon a Bootstrap Icons name, beside the heading
     * @param string|null $class added to the column
     */
    public function __construct(
        public int|Span|null $cols = null,
        public array $children = [],
        public ?string $name = null,
        public ?string $icon = null,
        public ?string $class = null,
    ) {
    }

    /** The width of the column; null = an equal share. */
    public function span(): ?Span
    {
        return is_int($this->cols) ? Span::from(self::BREAKPOINT, $this->cols) : $this->cols;
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
        return new self($this->cols, $children, $this->name, $this->icon, $this->class);
    }
}
