<?php

declare(strict_types=1);

namespace Byfareska\Crud\AssetMapper;

use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;

/**
 * The application's importmap.php with the panel's entries added - in memory only: what is written back
 * (importmap:require, :remove, :update) is the application's own entries. So the panel works through the
 * application's AssetMapper with nothing to configure:
 *
 * - '@byfareska/crud/panel.importmap.js', an entrypoint: the script byfareska_crud.assets "auto" draws a panel with
 *   when the application names no entries - the importmap of the page maps the modules it imports, relative ones too;
 * - '@byfareska/crud': the controllers of the bundle by name, for an application with entries of its own -
 *   registerControllers(startStimulusApp()) in its Stimulus bootstrap.
 *
 * An entry of the same name in the application's importmap.php (an older setup) wins and stays its own.
 *
 * Put in place of AssetMapper's reader by ImportMapPanelPass, only for AssetMapper with StimulusBundle.
 */
class PanelImportMapConfigReader extends ImportMapConfigReader
{
    public const string ENTRY = '@byfareska/crud/panel.importmap.js';

    public const string MODULE = '@byfareska/crud';

    /** Name => [logical path, entrypoint]. */
    private const array ENTRIES = [
        self::ENTRY => ['@byfareska/crud/panel.importmap.js', true],
        self::MODULE => ['@byfareska/crud/index.js', false],
    ];

    /** @var array<string, true> the entries this reader added, not the application */
    private array $added = [];

    public function getEntries(): ImportMapEntries
    {
        $entries = parent::getEntries();
        foreach (self::ENTRIES as $name => [$path, $entrypoint]) {
            if (!$entries->has($name)) {
                $entries->add(ImportMapEntry::createLocal($name, ImportMapType::JS, $path, $entrypoint));
                $this->added[$name] = true;
            }
        }

        return $entries;
    }

    public function writeEntries(ImportMapEntries $entries): void
    {
        foreach (array_keys($this->added) as $name) {
            if ($entries->has($name)) {
                $entries->remove($name);
            }
        }
        $this->added = [];
        parent::writeEntries($entries);
    }
}
