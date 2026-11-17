<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional\App;

use Byfareska\Crud\CrudBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * An application of the bundle, as one is set up: FrameworkBundle with its translator and translation files of its
 * own, Doctrine ORM (SQLite in memory), the panel "panel" imported, services of its own autoconfigured.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $defaultLocale, private readonly string $cacheRoot)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new StimulusBundle();
        yield new TwigComponentBundle();
        yield new LiveComponentBundle();
        yield new CrudBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return $this->cacheRoot . '/cache';
    }

    public function getBuildDir(): string
    {
        return $this->cacheRoot . '/build';
    }

    public function getLogDir(): string
    {
        return $this->cacheRoot . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => $this->defaultLocale,
            'enabled_locales' => ['pl', 'en'],
            'translator' => ['default_path' => __DIR__ . '/translations', 'fallbacks' => ['en']],
            'csrf_protection' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router' => ['utf8' => true],
        ]);
        $container->extension('twig', ['default_path' => __DIR__ . '/templates']);
        $container->extension('doctrine', [
            'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true],
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => __NAMESPACE__ . '\\Entity',
                    ],
                ],
            ],
        ]);
        $container->extension('twig_component', ['defaults' => [], 'anonymous_template_directory' => 'components/']);
        $container->extension('byfareska_crud', ['assets' => 'none']);

        // The services of the application, as its config/services.yaml loads them: autoconfigured, no tags by hand.
        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();
        $services->load(__NAMESPACE__ . '\\', __DIR__ . '/')
            ->exclude([__DIR__ . '/TestKernel.php', __DIR__ . '/{translations,templates}']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('panel', 'byfareska_crud');
    }
}
