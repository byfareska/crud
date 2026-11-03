<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Theme;

use Byfareska\Crud\Theme\ThemeLoader;
use Byfareska\Crud\Theme\Themes;
use Byfareska\Crud\Theme\ThemeSelector;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\LoaderError;

final class ThemeLoaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/crud-themes-' . bin2hex(random_bytes(4));
        $this->write('base/panel/page.html.twig', 'base page {% block body %}base body{% endblock %}');
        $this->write('base/components/Ui/Box.html.twig', 'base box');
        $this->write('other/panel/page.html.twig', 'other page');
        // The application's theme: only one block of its parent's page.
        $this->write(
            'app/panel/page.html.twig',
            "{% extends '@CrudTheme.base/panel/page.html.twig' %}{% block body %}app body{% endblock %}",
        );
    }

    protected function tearDown(): void
    {
        /** @var \SplFileInfo $file */
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            ) as $file
        ) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testTheThemeInUseThenItsParents(): void
    {
        $themes = $this->themes('app');
        $twig = new Environment(new ThemeLoader($themes));

        self::assertSame('base page app body', $twig->render('@CrudTheme/panel/page.html.twig'));
        // Not in the application's theme: its parent's.
        self::assertSame('base box', $twig->render('components/Ui/Box.html.twig'));

        $themes->use('other');
        self::assertSame('other page', $twig->render('@CrudTheme/panel/page.html.twig'));
        // "other" has no parent.
        self::assertFalse($twig->getLoader()->exists('components/Ui/Box.html.twig'));

        $themes->reset();
        self::assertSame('app', $themes->current());
    }

    public function testNamesThatAreNotOfThePanel(): void
    {
        $loader = new ThemeLoader($this->themes('base'));

        self::assertFalse($loader->exists('panel/page.html.twig'));
        self::assertFalse($loader->exists('@CrudTheme/../base/panel/page.html.twig'));
        self::assertFalse($loader->exists('@CrudTheme.nope/panel/page.html.twig'));
        self::assertFalse($loader->exists('components/App/Box.html.twig'));
        $this->expectException(LoaderError::class);
        $loader->getSourceContext('@CrudTheme/panel/missing.html.twig');
    }

    public function testAThemeThatIsNotThere(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->themes('base')->use('nope');
    }

    public function testParentsInACircle(): void
    {
        $themes = new Themes(
            [
                'a' => ['path' => $this->root . '/base', 'parent' => 'b'],
                'b' => ['path' => $this->root . '/other', 'parent' => 'a'],
            ],
            $this->selector('a'),
        );

        $this->expectException(LogicException::class);
        $themes->directories();
    }

    public function testTheThemeTheSelectorChoosesWhenThePageIsDrawn(): void
    {
        // The panel of the page, known only once the request is.
        $selector = $this->selector('base');
        $themes = $this->themes($selector);
        $twig = new Environment(new ThemeLoader($themes));
        self::assertSame('base page base body', $twig->render('@CrudTheme/panel/page.html.twig'));

        $selector->theme = 'other';
        self::assertSame('other page', $twig->render('@CrudTheme/panel/page.html.twig'));

        // What the request chose is ahead of it.
        $themes->use('app');
        self::assertSame('base page app body', $twig->render('@CrudTheme/panel/page.html.twig'));
    }

    public function testAFileTwoThemesShareFindsItsParentInEachOfThem(): void
    {
        // "app" changes only the frame; the page is base's, in both themes.
        $this->write('base/crud/frame.html.twig', 'base frame {% block body %}{% endblock %}');
        $this->write(
            'base/crud/show.html.twig',
            "{% extends '@CrudTheme/crud/frame.html.twig' %}{% block body %}show{% endblock %}",
        );
        $this->write('app/crud/frame.html.twig', 'app frame {% block body %}{% endblock %}');
        $themes = $this->themes('app');
        // One environment for many requests, as in a worker.
        $twig = new Environment(new ThemeLoader($themes));

        self::assertSame('app frame show', $twig->render('@CrudTheme/crud/show.html.twig'));
        $themes->use('base');
        self::assertSame('base frame show', $twig->render('@CrudTheme/crud/show.html.twig'));
    }

    private function themes(string|ThemeSelector $current): Themes
    {
        return new Themes([
            'base' => ['path' => $this->root . '/base', 'parent' => null],
            'other' => ['path' => $this->root . '/other', 'parent' => null],
            'app' => ['path' => $this->root . '/app', 'parent' => 'base'],
        ], is_string($current) ? $this->selector($current) : $current);
    }

    private function selector(string $theme): SettableThemeSelector
    {
        return new SettableThemeSelector($theme);
    }

    private function write(string $file, string $content): void
    {
        $path = $this->root . '/' . $file;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o777, true);
        }
        file_put_contents($path, $content);
    }
}
