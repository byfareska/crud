<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\DependencyInjection;

use Byfareska\Crud\DependencyInjection\ComponentCollisionPass;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Loader\FilesystemLoader;

final class ComponentCollisionPassTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/crud-collision-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/templates/components/Ui', recursive: true);
        mkdir($this->directory . '/theme/components/Ui', recursive: true);
        touch($this->directory . '/templates/components/Ui/Banner.html.twig');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testAComponentOfItsOwnNamedAsNoneOfTheBundleIsFine(): void
    {
        new ComponentCollisionPass()->process($this->container());

        $this->expectNotToPerformAssertions();
    }

    public function testOneNamedAsAComponentOfTheBundleIsSaidAtOnce(): void
    {
        touch($this->directory . '/templates/components/Ui/Modal.html.twig');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('components/Ui/Modal.html.twig');

        new ComponentCollisionPass()->process($this->container());
    }

    public function testAThemeOfTheApplicationAmongItsTemplatesIsNoCollision(): void
    {
        // Its own Ui:Modal for the panel, in a theme: what themes are for.
        touch($this->directory . '/theme/components/Ui/Modal.html.twig');
        $container = $this->container();
        $container->getDefinition('twig.loader.native_filesystem')
            ->addMethodCall('addPath', [$this->directory . '/theme']);

        new ComponentCollisionPass()->process($container);

        $this->expectNotToPerformAssertions();
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('byfareska_crud.themes', [
            'bootstrap5' => ['path' => dirname(__DIR__, 3) . '/templates/bootstrap5', 'parent' => null],
            'acme' => ['path' => $this->directory . '/theme', 'parent' => 'bootstrap5'],
        ]);
        $container->register('twig.loader.native_filesystem', FilesystemLoader::class)
            ->addMethodCall('addPath', [$this->directory . '/templates', FilesystemLoader::MAIN_NAMESPACE, false]);

        return $container;
    }
}
