<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use InvalidArgumentException;

/**
 * How much of the dashboard a widget takes, in cells of its grid: 12 columns (byfareska_crud.dashboard.columns), a row
 * of 80 px. The presets are the sizes of Apple's widgets on 12 columns, made as wide on another grid; between the
 * smallest and the largest size of a widget it can be resized freely.
 *
 * On another grid than 12 columns the sizes a widget declares are cells of that grid.
 */
final readonly class WidgetSize
{
    /** The columns of the grid by default (byfareska_crud.dashboard.columns), what the presets are made for. */
    public const int COLUMNS = 12;

    /** The most columns a grid may have. */
    public const int MAX_COLUMNS = 48;

    public function __construct(
        public int $w,
        public int $h,
    ) {
        if ($w < 1 || $w > self::MAX_COLUMNS || $h < 1) {
            throw new InvalidArgumentException(sprintf(
                'A widget is 1-%d columns wide and at least a row high, not %dx%d.',
                self::MAX_COLUMNS,
                $w,
                $h,
            ));
        }
    }

    public static function small(): self
    {
        return new self(3, 2);
    }

    public static function medium(): self
    {
        return new self(6, 2);
    }

    public static function large(): self
    {
        return new self(6, 4);
    }

    public static function extraLarge(): self
    {
        return new self(12, 4);
    }

    /**
     * The size kept between the two: never smaller than $min, never larger than $max.
     */
    public function clamp(self $min, self $max): self
    {
        return new self(
            max($min->w, min($max->w, $this->w)),
            max($min->h, min($max->h, $this->h)),
        );
    }

    /**
     * The same share of a grid of $columns as of one of 12 (a preset on another grid).
     */
    public function onGridOf(int $columns): self
    {
        return $columns === self::COLUMNS
            ? $this
            : new self(max(1, min($columns, (int) round($this->w * $columns / self::COLUMNS))), $this->h);
    }

    public function equals(self $other): bool
    {
        return $this->w === $other->w && $this->h === $other->h;
    }
}
