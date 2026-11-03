<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * Makes a layout one that can be shown to the one acting: what no source offers any more, or offers only to a role
 * they do not have, is left out, sizes are kept between the smallest and the largest of the widget, places within
 * the 12 columns, settings to those the widget has; a widget overlapping another goes to the first free place.
 * Run on every read and every save, so a stored layout never has to be migrated.
 */
final readonly class LayoutNormalizer
{
    public function __construct(
        private LayoutPacker $packer = new LayoutPacker(),
    ) {
    }

    /**
     * @param list<WidgetPlacement> $placements
     * @param array<string, DashboardWidget> $widgets what the one acting may have, by key
     *
     * @return list<WidgetPlacement>
     */
    public function normalize(array $placements, array $widgets): array
    {
        $kept = [];
        $ids = [];
        foreach ($placements as $placement) {
            $widget = $widgets[$placement->widget] ?? null;
            if ($widget === null || isset($ids[$placement->id])) {
                continue;
            }
            $ids[$placement->id] = true;

            $columns = $this->packer->columns;
            $size = new WidgetSize(max(1, min($columns, $placement->w)), max(1, $placement->h))
                ->clamp($widget->minSize, $widget->maxSize);
            $x = max(0, min($columns - $size->w, $placement->x));
            $y = max(0, $placement->y);

            $kept[] = new WidgetPlacement(
                $placement->id,
                $placement->widget,
                $x,
                $y,
                $size->w,
                $size->h,
                $widget->settingsOf($placement->settings),
            );
        }

        // From the top, so what overlaps is the one lower down.
        usort($kept, static fn (WidgetPlacement $a, WidgetPlacement $b): int => [$a->y, $a->x] <=> [$b->y, $b->x]);

        $placed = [];
        foreach ($kept as $placement) {
            if ($this->packer->collides($placement, $placed)) {
                $placement = $this->packer->place(
                    $placed,
                    $placement->widget,
                    new WidgetSize($placement->w, $placement->h),
                    $placement->settings,
                    $placement->id,
                );
            }
            $placed[] = $placement;
        }

        return $placed;
    }
}
