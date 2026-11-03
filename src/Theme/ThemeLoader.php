<?php

declare(strict_types=1);

namespace Byfareska\Crud\Theme;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Finds the templates of the panel in the theme in use (see {@see Themes}), at the time they are rendered:
 *
 *   @CrudTheme/crud/list.html.twig: the theme in use, then its parents
 *   @CrudTheme.bootstrap5/crud/list.html.twig: that theme, then its parents - what a template of a theme extends to
 *     change only some of the blocks of its parent's
 *   components/Ui/Modal.html.twig: the components of the bundle (Ui:*, Crud:*, Panel:*), anonymous or not
 *
 * The compiled templates are told apart by the file they come from, so two themes never share one.
 */
#[AutoconfigureTag('twig.loader', ['priority' => 10])]
final class ThemeLoader implements LoaderInterface
{
    public const string NAMESPACE = '@CrudTheme';

    private const string COMPONENTS = '~^components/(?:Ui|Crud|Panel)/~';

    /** @var array<string, string|null> found files, by theme and name */
    private array $found = [];

    public function __construct(
        private readonly Themes $themes,
    ) {
    }

    public function getSourceContext(string $name): Source
    {
        $path = $this->path($name);

        return new Source((string) file_get_contents($path), $name, $path);
    }

    public function getCacheKey(string $name): string
    {
        return $this->path($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return filemtime($this->path($name)) < $time;
    }

    public function exists(string $name): bool
    {
        return $this->find($name) !== null;
    }

    private function path(string $name): string
    {
        return $this->find($name)
            ?? throw new LoaderError(sprintf(
                'Template "%s" is not in the panel theme "%s" nor its parents (%s).',
                $name,
                $this->themes->current(),
                implode(', ', $this->themes->directories()),
            ));
    }

    private function find(string $name): ?string
    {
        [$theme, $file] = $this->parse($name) ?? [null, null];
        if ($file === null) {
            return null;
        }
        $theme ??= $this->themes->current();
        $key = $theme . "\0" . $file;
        if (!array_key_exists($key, $this->found)) {
            $this->found[$key] = null;
            foreach ($this->themes->directories($theme) as $directory) {
                if (is_file($directory . '/' . $file)) {
                    $this->found[$key] = $directory . '/' . $file;
                    break;
                }
            }
        }

        return $this->found[$key];
    }

    /**
     * @return array{string|null, string}|null the theme named (null = the one in use) and the file; null = not a name
     *     of the panel
     */
    private function parse(string $name): ?array
    {
        $name = ltrim(str_replace('\\', '/', $name), '/');
        if (preg_match(self::COMPONENTS, $name) === 1) {
            return [null, $name];
        }
        if (!str_starts_with($name, self::NAMESPACE)) {
            return null;
        }
        $slash = strpos($name, '/');
        if ($slash === false) {
            return null;
        }
        $namespace = substr($name, 0, $slash);
        $file = substr($name, $slash + 1);
        // No way out of the theme's directory.
        if ($file === '' || str_contains('/' . $file . '/', '/../')) {
            return null;
        }
        if ($namespace === self::NAMESPACE) {
            return [null, $file];
        }
        if (str_starts_with($namespace, self::NAMESPACE . '.')) {
            $theme = substr($namespace, strlen(self::NAMESPACE) + 1);

            return in_array($theme, $this->themes->names(), true) ? [$theme, $file] : null;
        }

        return null;
    }
}
