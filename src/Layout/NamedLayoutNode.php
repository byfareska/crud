<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * A part of a layout of the application's own that names its block itself, instead of by its short class name:
 * two classes named alike in two modules (App\Billing\Notice, App\Crm\Notice) would both be layout_notice.
 *
 *     public static function blockName(): string { return 'billing_notice'; } // the block layout_billing_notice
 */
interface NamedLayoutNode extends LayoutNode
{
    /** What follows "layout_" in the name of its block: lower-case letters, digits and "_". */
    public static function blockName(): string;
}
