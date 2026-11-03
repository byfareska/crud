<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * A part of the layout that holds others: a tab, a column, a fieldset or a row.
 */
interface LayoutGroup extends LayoutNode
{
    /**
     * Lower-case; the translation keys crud.<resource>.layout.<name>.name and .help, the id of a tab in the address
     * (?tab=<name>) and the most specific Twig block (crud_<resource>_layout_<name>). Null for a group without a
     * heading.
     */
    public function name(): ?string;

    /**
     * @return list<LayoutNode|string>
     */
    public function children(): array;

    /**
     * The same group holding other children - what is left of it on a page that lacks some of its fields.
     *
     * @param list<LayoutNode|string> $children
     */
    public function with(array $children): static;
}
