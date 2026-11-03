<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Byfareska\Crud\Attribute\AsDashboardWidget;
use LogicException;
use ReflectionClass;

/**
 * The widgets declared with #[AsDashboardWidget] on their components (collected by CollectWidgetsPass).
 */
final class AttributeDashboardWidgets implements DashboardWidgetSource
{
    /** @var list<DashboardWidget>|null */
    private ?array $widgets = null;

    /**
     * @param array<class-string, string> $components the name of the component by its class
     */
    public function __construct(
        private readonly array $components = [],
    ) {
    }

    public function widgets(): iterable
    {
        if ($this->widgets === null) {
            $this->widgets = [];
            foreach ($this->components as $class => $component) {
                foreach (self::attributesOf($class) as $attribute) {
                    $this->widgets[] = $attribute->widget($attribute->component ?? $component);
                }
            }
        }

        return $this->widgets;
    }

    /**
     * @param class-string $class
     */
    public static function attributeOf(string $class): AsDashboardWidget
    {
        return self::attributesOf($class)[0];
    }

    /**
     * Every #[AsDashboardWidget] of the class: one component may be several widgets.
     *
     * @param class-string $class
     *
     * @return non-empty-list<AsDashboardWidget>
     */
    public static function attributesOf(string $class): array
    {
        $attributes = new ReflectionClass($class)->getAttributes(AsDashboardWidget::class);
        if ($attributes === []) {
            throw new LogicException(sprintf('"%s" has no #[AsDashboardWidget].', $class));
        }

        return array_map(
            static fn (\ReflectionAttribute $attribute): AsDashboardWidget => $attribute->newInstance(),
            $attributes,
        );
    }
}
