<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use InvalidArgumentException;

/**
 * A widget offered for the dashboards: a Twig component, given the id of its place on the dashboard (`placement`),
 * the dashboard (`board`, the name of its panel) and its `settings` - see DashboardWidgetProps. The dashboards collect
 * them from every DashboardWidgetSource.
 */
final readonly class DashboardWidget
{
    public const string CATEGORY_GENERAL = 'ui.dashboard.category.general';
    public const string CATEGORY_USERS = 'ui.dashboard.category.users';
    public const string CATEGORY_SALES = 'ui.dashboard.category.sales';
    public const string CATEGORY_CONTENT = 'ui.dashboard.category.content';
    public const string CATEGORY_SYSTEM = 'ui.dashboard.category.system';

    public WidgetSize $minSize;

    public WidgetSize $maxSize;

    /**
     * @param string $key stable, e.g. "sales.orders"; what a stored layout names
     * @param string $component name of a Twig component, e.g. "Sales:OrdersWidget"
     * @param string $title translation key
     * @param string $description translation key
     * @param string $icon Bootstrap Icons name without "bi-"
     * @param string $accent a CSS colour, e.g. "#8b5cf6" - the icon of the card
     * @param string $category translation key, one of the CATEGORY_* or one of the application
     * @param string|null $role null = everybody signed in
     * @param bool $default part of the layout every user starts with
     * @param int $priority lower comes first in that layout and in the gallery
     * @param list<WidgetSetting> $settings
     * @param bool $lazy a live component loaded after the page (its placeholder first); false for a plain Twig
     *     component, rendered with the page
     * @param list<string>|null $boards the dashboards (panels, route scopes) that offer it; null = every one. "default"
     *     puts it into the layout of each of them
     * @param string|null $tone a hint for a theme that paints cards in colours of their own (a name such as "blue",
     *     "yellow", "night"); the bundle's themes do not
     * @param array<string, mixed> $props what the component is given besides its place and settings
     */
    public function __construct(
        public string $key,
        public string $component,
        public string $title,
        public string $description,
        public string $icon,
        public string $accent = '#8b5cf6',
        public string $category = self::CATEGORY_GENERAL,
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
        public array $props = [],
    ) {
        $this->minSize = $minSize ?? $size;
        $this->maxSize = $maxSize ?? $size;
    }

    /**
     * This widget with other settings (those whose choices were filled in).
     *
     * @param list<WidgetSetting> $settings
     */
    public function withSettings(array $settings): self
    {
        return $this->with(settings: $settings);
    }

    /**
     * This widget with some of it changed, by the names of the arguments of the constructor - what a
     * DashboardWidgetFilter or a DashboardWidgetSource changes: `$widget->with(title: ..., props: [...])`. Whatever is
     * not named stays as it is.
     */
    public function with(mixed ...$changes): self
    {
        $current = [
            'key' => $this->key,
            'component' => $this->component,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'accent' => $this->accent,
            'category' => $this->category,
            'size' => $this->size,
            'minSize' => $this->minSize,
            'maxSize' => $this->maxSize,
            'role' => $this->role,
            'default' => $this->default,
            'priority' => $this->priority,
            'settings' => $this->settings,
            'lazy' => $this->lazy,
            'tone' => $this->tone,
            'boards' => $this->boards,
            'props' => $this->props,
        ];
        $unknown = array_diff(array_keys($changes), array_keys($current));
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'A widget has no %s to change.',
                implode(', ', array_map(strval(...), $unknown)),
            ));
        }

        // @phpstan-ignore argument.type (named arguments of the constructor, which checks them)
        return new self(...array_replace($current, $changes));
    }

    /**
     * The settings with their defaults, then what was given for those of them that exist.
     *
     * @param array<array-key, mixed> $given
     *
     * @return array<string, string|list<string>>
     */
    public function settingsOf(array $given): array
    {
        $values = [];
        foreach ($this->settings as $setting) {
            $values[$setting->name] = array_key_exists($setting->name, $given)
                ? $setting->accept($given[$setting->name])
                : $setting->default;
        }

        return $values;
    }

    /**
     * Whether a dashboard offers it.
     */
    public function isOn(string $board): bool
    {
        return $this->boards === null || in_array($board, $this->boards, true);
    }

    /**
     * Whether it has settings to change in the dialog (the gear in edit mode).
     */
    public function isConfigurable(): bool
    {
        return array_any($this->settings, fn(WidgetSetting $setting): bool => $setting->isEditable());
    }

    /**
     * The sizes of Apple's widgets this one may take, by their names (s, m, l, xl), on a grid of $columns.
     *
     * @return array<string, WidgetSize>
     */
    public function presets(int $columns = WidgetSize::COLUMNS): array
    {
        $presets = [];
        foreach (
            [
            's' => WidgetSize::small()->onGridOf($columns),
            'm' => WidgetSize::medium()->onGridOf($columns),
            'l' => WidgetSize::large()->onGridOf($columns),
            'xl' => WidgetSize::extraLarge()->onGridOf($columns),
            ] as $name => $preset
        ) {
            if ($preset->clamp($this->minSize, $this->maxSize)->equals($preset)) {
                $presets[$name] = $preset;
            }
        }

        return $presets === [] ? ['m' => $this->size] : $presets;
    }
}
