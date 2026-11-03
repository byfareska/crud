<?php

declare(strict_types=1);

namespace Byfareska\Crud\DependencyInjection;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Attribute\AsCrudDetailsSection;
use Byfareska\Crud\Attribute\AsCrudFormSection;
use Byfareska\Crud\Attribute\AsCrudPage;
use Byfareska\Crud\Resource\CrudRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Hands CrudRegistry the classes that carry #[AsCrud], #[AsCrudFormSection], #[AsCrudDetailsSection] and
 * #[AsCrudPage] - by name, so they need not be services. That takes in classes of directories excluded from the
 * services as well (entities: a resource can be declared on the entity itself), which Symfony keeps as definitions
 * tagged "container.excluded".
 */
final class CollectCrudDeclarationsPass implements CompilerPassInterface
{
    private const array ATTRIBUTES = [
        AsCrud::class => [AsCrud::TAG, '$resources'],
        AsCrudFormSection::class => [AsCrudFormSection::TAG, '$formSections'],
        AsCrudDetailsSection::class => [AsCrudDetailsSection::TAG, '$detailsSections'],
        AsCrudPage::class => [AsCrudPage::TAG, '$pages'],
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CrudRegistry::class)) {
            return;
        }

        $found = array_fill_keys(array_keys(self::ATTRIBUTES), []);
        foreach ($container->getDefinitions() as $id => $definition) {
            $tagged = array_any(
                self::ATTRIBUTES,
                static fn (array $attribute): bool => $definition->hasTag($attribute[0]),
            );
            if (!$tagged && !$definition->hasTag('container.excluded')) {
                continue;
            }
            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);
            $reflection = is_string($class) ? $container->getReflectionClass($class, false) : null;
            if (!$reflection instanceof \ReflectionClass) {
                continue;
            }
            foreach (array_keys(self::ATTRIBUTES) as $attribute) {
                if ($reflection->getAttributes($attribute) !== []) {
                    $found[$attribute][$reflection->getName()] = true;
                }
            }
        }

        $registry = $container->getDefinition(CrudRegistry::class);
        foreach (self::ATTRIBUTES as $attribute => [, $argument]) {
            $classes = array_keys($found[$attribute]);
            sort($classes);
            $registry->setArgument($argument, $classes);
        }
    }
}
