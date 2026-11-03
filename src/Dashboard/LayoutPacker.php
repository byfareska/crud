<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Puts widgets onto the grid where there is room: row by row from the top, left to right in a row - the first place
 * where the widget overlaps nothing.
 */
final readonly class LayoutPacker
{
    /**
     * @param int $columns of the grid (byfareska_crud.dashboard.columns)
     */
    public function __construct(
        #[Autowire(param: 'byfareska_crud.dashboard.columns')]
        public int $columns = WidgetSize::COLUMNS,
    ) {
    }

    /**
     * The layout made of the code: the widgets marked as default, by priority.
     *
     * @param iterable<DashboardWidget> $widgets
     *
     * @return list<WidgetPlacement>
     */
    public function pack(iterable $widgets): array
    {
        $chosen = [];
        foreach ($widgets as $widget) {
            if ($widget->default) {
                $chosen[] = $widget;
            }
        }
        usort($chosen, static fn (DashboardWidget $a, DashboardWidget $b): int => $a->priority <=> $b->priority);

        $placements = [];
        foreach ($chosen as $widget) {
            // Made anew on every read, so the id has to be the same every time: a widget is here once, by its key.
            $placements[] = $this->place(
                $placements,
                $widget->key,
                $widget->size,
                $widget->settingsOf([]),
                str_replace('.', '-', $widget->key),
            );
        }

        return $placements;
    }

    /**
     * A new placement of the widget in the first free place.
     *
     * @param list<WidgetPlacement> $placements what is on the grid already
     * @param array<string, string|list<string>> $settings
     */
    public function place(
        array $placements,
        string $widget,
        WidgetSize $size,
        array $settings = [],
        ?string $id = null,
    ): WidgetPlacement {
        $candidate = new WidgetPlacement($id ?? WidgetPlacement::newId(), $widget, 0, 0, $size->w, $size->h, $settings);
        for ($y = 0;; ++$y) {
            for ($x = 0; $x + $size->w <= $this->columns; ++$x) {
                $candidate = $candidate->movedTo($x, $y, $size->w, $size->h);
                if (!$this->collides($candidate, $placements)) {
                    return $candidate;
                }
            }
        }
    }

    /**
     * @param list<WidgetPlacement> $placements
     */
    public function collides(WidgetPlacement $candidate, array $placements): bool
    {
        return array_any(
            $placements,
            fn(WidgetPlacement $placed): bool => $placed->id !== $candidate->id && $placed->overlaps($candidate),
        );
    }
}
