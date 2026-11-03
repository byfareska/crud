<?php

declare(strict_types=1);

namespace Byfareska\Crud\DependencyInjection;

use Byfareska\Crud\Attribute\AsDashboardWidget;
use Byfareska\Crud\Attribute\AsTopbarWidget;
use Byfareska\Crud\Dashboard\AttributeDashboardWidgets;
use Byfareska\Crud\Navigation\AttributeTopbarWidgets;
use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Hands AttributeDashboardWidgets and AttributeTopbarWidgets the components that carry #[AsDashboardWidget] and
 * #[AsTopbarWidget]: their classes and the names they are rendered by (that of their #[AsTwigComponent], tag
 * "twig.component", unless the attribute names one).
 */
final class CollectWidgetsPass implements CompilerPassInterface
{
    private const string COMPONENT_TAG = 'twig.component';

    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(AttributeDashboardWidgets::class)) {
            $components = $this->collect($container, AsDashboardWidget::class, AsDashboardWidget::TAG);
            $this->ensureUniqueKeys($components);
            $container->getDefinition(AttributeDashboardWidgets::class)->setArgument('$components', $components);
        }
        if ($container->hasDefinition(AttributeTopbarWidgets::class)) {
            $container->getDefinition(AttributeTopbarWidgets::class)
                ->setArgument('$components', $this->collect($container, AsTopbarWidget::class, AsTopbarWidget::TAG));
        }
    }

    /**
     * @param class-string<AsDashboardWidget|AsTopbarWidget> $attribute
     *
     * @return array<class-string, string> the name of the component by its class, in the order of the classes
     */
    private function collect(ContainerBuilder $container, string $attribute, string $tag): array
    {
        $components = [];
        foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
            $definition = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);
            $reflection = is_string($class) ? $container->getReflectionClass($class, false) : null;
            $attributes = $reflection?->getAttributes($attribute) ?? [];
            if (!$reflection instanceof \ReflectionClass || $attributes === []) {
                continue;
            }

            $name = $attributes[0]->newInstance()->component;
            foreach ($definition->getTag(self::COMPONENT_TAG) as $component) {
                $key = is_array($component) ? $component['key'] ?? null : null;
                $name ??= is_string($key) ? $key : null;
            }
            if ($name === null) {
                throw new LogicException(sprintf(
                    '#[%s] on "%s" needs the name of its component: give #[AsTwigComponent] one, or the attribute '
                    . '"component".',
                    substr($attribute, strrpos($attribute, '\\') + 1),
                    $reflection->getName(),
                ));
            }
            $components[$reflection->getName()] = $name;
        }
        ksort($components);

        return $components;
    }

    /**
     * @param array<class-string, string> $components
     */
    private function ensureUniqueKeys(array $components): void
    {
        $classes = [];
        foreach (array_keys($components) as $class) {
            foreach (AttributeDashboardWidgets::attributesOf($class) as $attribute) {
                $key = $attribute->key;
                if (isset($classes[$key])) {
                    throw new LogicException(sprintf(
                        'Two dashboard widgets have the key "%s": "%s" and "%s".',
                        $key,
                        $classes[$key],
                        $class,
                    ));
                }
                $classes[$key] = $class;
            }
        }
    }
}
