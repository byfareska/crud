<?php

declare(strict_types=1);

namespace Byfareska\Crud\DependencyInjection;

use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Translation\CrudDefaultTexts;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Puts the texts made of the names of the resources (CrudDefaultTexts) first among the translation files of the
 * translator, before the files of the bundles and of the application - which so win over them - in the default
 * locale and in English, the ones others fall back to.
 *
 * They go into the "resource_files" option of FrameworkBundle's translator, not into a call of addResource(): the
 * first addResource() of that translator adds every file of "resource_files" before the resource it is called with,
 * which would put the texts last, over every translation. A file name there is "domain.locale.format"; the format is
 * the loader (CrudDefaultTexts), which reads no file, so the path names none.
 */
final class CrudDefaultTextsPass implements CompilerPassInterface
{
    /**
     * Part of the name of the cached catalogues: those of v0.2.0, with the texts last, are not read again. The
     * translation files did not change, so their metadata would keep them fresh.
     */
    public const string CACHE_VARY = 'byfareska_crud.default_texts';

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

        $default = $container->getParameterBag()->resolveValue('%kernel.default_locale%');
        $locales = array_values(array_unique([is_string($default) ? $default : 'en', 'en']));
        $translator = $container->getDefinition('translator.default');
        $options = $translator->getArgument(4);
        if (!is_array($options)) {
            return;
        }

        $files = is_array($options['resource_files'] ?? null) ? $options['resource_files'] : [];
        foreach ($locales as $locale) {
            $files[$locale] = [
                sprintf('byfareska-crud/messages.%s.%s', $locale, CrudDefaultTexts::FORMAT),
                ...(is_array($files[$locale] ?? null) ? $files[$locale] : []),
            ];
        }
        $options['resource_files'] = $files;
        $vary = is_array($options['cache_vary'] ?? null) ? $options['cache_vary'] : [];
        $options['cache_vary'] = [...$vary, self::CACHE_VARY => 'first'];
        $translator->replaceArgument(4, $options);
    }
}
