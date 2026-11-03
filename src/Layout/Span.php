<?php

declare(strict_types=1);

namespace Byfareska\Crud\Layout;

use LogicException;

/**
 * How much of the width a Column or a Field in a Row takes, in 12ths, per breakpoint of the screen - each width holds
 * from its breakpoint up, until a larger one says otherwise; below the smallest one given, it takes the whole width.
 * Every theme draws it with its own grid, e.g. new Span(sm: 6, lg: 4) is "col-sm-6 col-lg-4" in bootstrap5 and
 * "col-span-12 sm:col-span-6 lg:col-span-4" in tailwind.
 */
final readonly class Span
{
    /** @var array<string, int> 12ths by breakpoint (its Breakpoint value), the smallest first */
    public array $widths;

    public function __construct(
        ?int $xs = null,
        ?int $sm = null,
        ?int $md = null,
        ?int $lg = null,
        ?int $xl = null,
        ?int $xxl = null,
    ) {
        $widths = array_filter(
            array_combine(
                array_map(static fn (Breakpoint $breakpoint): string => $breakpoint->value, Breakpoint::cases()),
                [$xs, $sm, $md, $lg, $xl, $xxl],
            ),
            static fn (?int $width): bool => $width !== null,
        );
        if ($widths === []) {
            throw new LogicException('A Span needs a width at one breakpoint at least.');
        }
        foreach ($widths as $breakpoint => $width) {
            if ($width < 1 || $width > 12) {
                throw new LogicException(sprintf(
                    'A width of the grid is 1 to 12 twelfths, not %d (at "%s").',
                    $width,
                    $breakpoint === '' ? 'xs' : $breakpoint,
                ));
            }
        }
        $this->widths = $widths;
    }

    /** One width from a breakpoint up - what a plain number of 12ths means. */
    public static function from(Breakpoint $breakpoint, int $width): self
    {
        // The arguments are named like the cases: xs, sm, md...
        return new self(...[strtolower($breakpoint->name) => $width]);
    }
}
