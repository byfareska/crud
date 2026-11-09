<?php

declare(strict_types=1);

namespace Byfareska\Crud\DependencyInjection;

use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Translation\CrudDefaultTexts;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Puts the texts made of the names of the resources (CrudDefaultTexts) first among the resources of the translator,
 * before the files of the bundles and of the application - which so win over them - in the default locale and in
 * English, the ones others fall back to.
 */
final class CrudDefaultTextsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CrudDefaultTexts::class) || !$container->hasDefinition('translator.default')) {
            return;
        }

        if ($container->hasDefinition(CrudRegistry::class)) {
            $container->getDefinition(CrudDefaultTexts::class)->setArgument(
                '$classes',
                $container->getDefinition(CrudRegistry::class)->getArgument('$resources'),
            );
        }

        $locales = array_values(array_unique([
            (string) $container->getParameterBag()->resolveValue('%kernel.default_locale%'),
            'en',
        ]));
        $translator = $container->getDefinition('translator.default');
        $calls = array_map(
            static fn (string $locale): array => [
                'addResource',
                [CrudDefaultTexts::FORMAT, CrudDefaultTexts::FORMAT, $locale, 'messages'],
            ],
            $locales,
        );
        $translator->setMethodCalls([...$calls, ...$translator->getMethodCalls()]);
    }
}
