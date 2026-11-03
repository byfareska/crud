<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;
use Byfareska\Crud\Dashboard\DashboardWidget;
use Byfareska\Crud\Dashboard\WidgetSetting;
use Byfareska\Crud\Dashboard\WidgetSize;

/**
 * Offers the Twig (or live) component it is put on to the dashboards: the {@see DashboardWidget} of that component,
 * named by its #[AsTwigComponent] / #[AsLiveComponent]. The arguments are those of DashboardWidget; a setting whose
 * choices come from a service names it with `choicesFrom` (see WidgetChoices).
 *
 * byfareska_crud.dashboard.disabled_widgets takes one out by its key. Repeatable: one component is several widgets of
 * keys of their own, each given its props - a "counter" of new orders and one of open tickets.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsDashboardWidget
{
    public const string TAG = 'byfareska_crud.dashboard_widget';

    public WidgetSize $minSize;

    public WidgetSize $maxSize;

    /**
     * @param list<WidgetSetting> $settings
     * @param list<string>|null $boards
     * @param string|null $component the name of the component; null = the one of its #[AsTwigComponent]
     * @param array<string, mixed> $props what the component is given besides its place and settings
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $icon,
        public string $accent = '#8b5cf6',
        public string $category = DashboardWidget::CATEGORY_GENERAL,
        public WidgetSize $size = new WidgetSize(3, 2),
        ?WidgetSize $minSize = null,
        ?WidgetSize $maxSize = null,
        public ?string $role = null,
        public bool $default = false,
        public int $priority = 0,
        public array $settings = [],
        public bool $lazy = true,
        public ?string $tone = null,
        public ?array $boards = null,
        public ?string $component = null,
        public array $props = [],
    ) {
        $this->minSize = $minSize ?? $size;
        $this->maxSize = $maxSize ?? $size;
    }

    public function widget(string $component): DashboardWidget
    {
        return new DashboardWidget(
            key: $this->key,
            component: $component,
            title: $this->title,
            description: $this->description,
            icon: $this->icon,
            accent: $this->accent,
            category: $this->category,
            size: $this->size,
            minSize: $this->minSize,
            maxSize: $this->maxSize,
            role: $this->role,
            default: $this->default,
            priority: $this->priority,
            settings: $this->settings,
            lazy: $this->lazy,
            tone: $this->tone,
            boards: $this->boards,
            props: $this->props,
        );
    }
}
