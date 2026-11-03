<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * A part of a layout of the application's own - an info box, a separator, a card of fields - that says where it may
 * stand (and, a group, what it may hold). It is drawn by the Twig block layout_<its short class name, lower-case>
 * (`final class Notice` - layout_notice) of the resource's theme or of @CrudTheme/crud/layout.html.twig, given
 * `node` - or by layout_<its blockName()> when it is a NamedLayoutNode too. One that holds nothing (no LayoutGroup) is
 * on every page it stands on.
 */
interface PlacedLayoutNode extends LayoutNode
{
    /**
     * Where it may stand: "top", or classes of the groups (Tab::class, Column::class, Fieldset::class, Row::class, or
     * one of the application's own).
     *
     * @return list<string>
     */
    public static function placedIn(): array;

    /**
     * What it may hold, when it is a LayoutGroup: classes of nodes (Field::class for fields); nothing else.
     *
     * @return list<class-string<LayoutNode>>
     */
    public static function holds(): array;
}
