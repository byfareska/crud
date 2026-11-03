<?php

declare(strict_types=1);

namespace Byfareska\Crud\DependencyInjection;

use Byfareska\Crud\Routing\PanelLiveUrls;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives LiveComponent {@see PanelLiveUrls} in place of the router where it makes the addresses of components: the
 * attributes of a component it renders and the live URL of Twig (component_url()).
 */
final class PanelLiveUrlsPass implements CompilerPassInterface
{
    private const array SERVICES = [
        'ux.live_component.live_controller_attributes_creator',
        'ux.live_component.twig.component_runtime',
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PanelLiveUrls::class)) {
            return;
        }
        foreach (self::SERVICES as $id) {
            if (!$container->hasDefinition($id)) {
                continue;
            }
            $definition = $container->getDefinition($id);
            foreach ($definition->getArguments() as $index => $argument) {
                if ($argument instanceof Reference && (string) $argument === 'router') {
                    $definition->replaceArgument($index, new Reference(PanelLiveUrls::class));
                }
            }
        }
    }
}
