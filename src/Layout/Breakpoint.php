<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

/**
 * A width of the screen from which a width of the grid holds, from the smallest up. The value is how the themes name
 * it ("" = every screen); see Span.
 */
enum Breakpoint: string
{
    case Xs = '';
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';
    case Xxl = 'xxl';
}
