<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Widget;

use Byfareska\Crud\Attribute\AsDashboardWidget;
use Byfareska\Crud\CommandPalette\PaletteEntry;
use Byfareska\Crud\Dashboard\DashboardWidgetProps;
use Byfareska\Crud\Dashboard\QuickActionChoices;
use Byfareska\Crud\Dashboard\QuickActions;
use Byfareska\Crud\Dashboard\WidgetSetting;
use Byfareska\Crud\Dashboard\WidgetSettingType;
use Byfareska\Crud\Dashboard\WidgetSize;
use Byfareska\Crud\Panel\ScopedComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Widget: tiles leading to the pages that add a record, like the shortcuts of iOS - which ones is a setting
 * (none chosen = all the one signed in may use).
 */
#[AsDashboardWidget(
    key: 'panel.quick_actions',
    title: 'panel.widget.quick_actions.title',
    description: 'panel.widget.quick_actions.description',
    icon: 'lightning-charge',
    accent: '#3b82f6',
    size: new WidgetSize(3, 2),
    minSize: new WidgetSize(2, 2),
    maxSize: new WidgetSize(6, 4),
    default: true,
    priority: 20,
    settings: [new WidgetSetting(
        'actions',
        'panel.widget.quick_actions.setting',
        WidgetSettingType::Multiple,
        default: [],
        choicesFrom: QuickActionChoices::NAME,
    )],
    lazy: false,
    tone: 'graphite',
)]
#[AsTwigComponent('Panel:QuickActionsWidget', template: 'components/Panel/QuickActionsWidget.html.twig')]
final class QuickActionsWidget implements ScopedComponent
{
    use DashboardWidgetProps;

    /** The colours of the tiles, one after another. */
    private const array COLORS = [
        '#3b82f6',
        '#8b5cf6',
        '#f97316',
        '#22c55e',
        '#ec4899',
        '#14b8a6',
        '#6366f1',
        '#ef4444',
    ];

    public function __construct(
        private readonly QuickActions $actions,
    ) {
    }

    /**
     * @return list<array{entry: PaletteEntry, icon: string, color: string}>
     */
    public function getTiles(): array
    {
        $chosen = $this->settings['actions'] ?? [];
        $tiles = [];
        $board = $this->board === '' ? null : $this->board;
        foreach ($this->actions->available(is_array($chosen) ? $chosen : [], $board) as $index => $action) {
            $tiles[] = [...$action, 'color' => self::COLORS[$index % count(self::COLORS)]];
        }

        return $tiles;
    }
}
