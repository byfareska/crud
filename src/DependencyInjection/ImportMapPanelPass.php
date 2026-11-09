<?php

declare(strict_types=1);

namespace Byfareska\Crud\DependencyInjection;

use Byfareska\Crud\AssetMapper\PanelImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * With AssetMapper and StimulusBundle, the panel's script is an entrypoint of the application's importmap
 * (PanelImportMapConfigReader) - unless the application put a reader of its own in place of AssetMapper's.
 */
final class ImportMapPanelPass implements CompilerPassInterface
{
    private const string READER = 'asset_mapper.importmap.config_reader';

    public function process(ContainerBuilder $container): void
    {
        if (
            !$container->hasParameter('byfareska_crud.stimulus_importmap')
            || $container->getParameter('byfareska_crud.stimulus_importmap') !== true
            || !$container->hasDefinition(self::READER)
        ) {
            return;
        }
        $reader = $container->getDefinition(self::READER);
        if ($reader->getClass() === ImportMapConfigReader::class) {
            $reader->setClass(PanelImportMapConfigReader::class);
        }
    }
}
