<?php

declare(strict_types=1);

namespace Byfareska\Crud\DependencyInjection;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Loader\FilesystemLoader;

/**
 * The components of the bundle (Ui:*, Crud:*, Panel:*) are drawn from the theme of the panel, ahead of the templates of
 * the application (ThemeLoader): a component of the application's own of the same name - its own Ui:Modal in
 * templates/components/Ui/Modal.html.twig - would never be drawn, on the panel nor anywhere else. Said when the
 * container is built rather than found out on a page: the application renames its component, or, to change the one of
 * the panel, puts it into a theme of its own (byfareska_crud.themes).
 */
final class ComponentCollisionPass implements CompilerPassInterface
{
    private const string LOADER = 'twig.loader.native_filesystem';

    private const array PREFIXES = ['Ui', 'Crud', 'Panel'];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::LOADER) || !$container->hasParameter('byfareska_crud.themes')) {
            return;
        }

        /** @var array<string, array{path: string, parent: string|null}> $themes */
        $themes = $container->getParameter('byfareska_crud.themes');
        $themePaths = array_map(static fn (array $theme): string => self::normalize($theme['path']), $themes);
        $ofTheBundle = [];
        foreach ($themePaths as $path) {
            foreach (self::PREFIXES as $prefix) {
                foreach (glob($path . '/components/' . $prefix . '/*.html.twig') ?: [] as $file) {
                    $ofTheBundle['components/' . $prefix . '/' . basename($file)] = true;
                }
            }
        }

        $collisions = [];
        foreach ($this->mainPaths($container) as $path) {
            // A theme kept among the templates of the application is the panel's, not a collision.
            if (in_array($path, $themePaths, true)) {
                continue;
            }
            foreach (array_keys($ofTheBundle) as $name) {
                if (is_file($path . '/' . $name)) {
                    $collisions[] = $path . '/' . $name;
                }
            }
        }

        if ($collisions !== []) {
            throw new LogicException(sprintf(
                'The application has components named as those of byfareska/crud (Ui:*, Crud:*, Panel:*), which the '
                . 'panel draws in their place everywhere: %s. Rename the application\'s own component, or - to change '
                . 'the one of the panel - move it into a theme of your own (byfareska_crud.themes).',
                implode(', ', $collisions),
            ));
        }
    }

    /**
     * The directories of the templates without a namespace ("base.html.twig"): the default one and twig.paths.
     *
     * @return list<string>
     */
    private function mainPaths(ContainerBuilder $container): array
    {
        $paths = [];
        foreach ($container->getDefinition(self::LOADER)->getMethodCalls() as $call) {
            /** @var array{0: string, 1?: list<mixed>} $call */
            [$method, $arguments] = $call + [1 => []];
            $namespace = $arguments[1] ?? FilesystemLoader::MAIN_NAMESPACE;
            if ($method !== 'addPath' || $namespace !== FilesystemLoader::MAIN_NAMESPACE || !isset($arguments[0])) {
                continue;
            }
            $path = $container->getParameterBag()->resolveValue($arguments[0]);
            if (is_string($path) && is_dir($path)) {
                $paths[] = self::normalize($path);
            }
        }

        return array_values(array_unique($paths));
    }

    private static function normalize(string $path): string
    {
        return rtrim(realpath($path) ?: $path, '/');
    }
}
