<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * A field of a page by its name: of the form, of the record on the details pages, or a section another part of the
 * application adds (#[AsCrudFormSection], #[AsCrudDetailsSection]). A plain 'name' in a layout means the same; this
 * class is for a field that takes a width of its own in a Row, or goes without its label.
 */
final readonly class Field implements LayoutNode
{
    /**
     * @param int|Span|null $cols 12ths of the row from its breakpoint up, or a Span for other breakpoints; only in a
     *     Row
     * @param bool $label false = without its own label, e.g. a section under a heading that says the same
     * @param string|null $class added to its cell of the row; only in a Row
     */
    public function __construct(
        public string $name,
        public int|Span|null $cols = null,
        public bool $label = true,
        public ?string $class = null,
    ) {
    }
}
