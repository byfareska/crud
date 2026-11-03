<?php

declare(strict_types=1);

namespace Byfareska\Crud\Resource;

use Byfareska\Crud\CommandPalette\CommandPaletteSource;
use Byfareska\Crud\CommandPalette\NavigationPaletteSource;
use Byfareska\Crud\CommandPalette\PaletteEntry;
use Byfareska\Crud\CommandPalette\PanelCommandsPaletteSource;
use Closure;

/**
 * The pages of every #[AsCrud] resource as entries of the command palette: its list and the page to add a record
 * (the page of one's own record for an "own" one), each in the palette of the panel of its route scope.
 *
 * The entries are those of the declarations, the same for everybody (an index is built of them); whether the one asking
 * is offered one is the resource as it is in the request: for them, and its operation not refused (CrudAccess).
 */
final readonly class CrudPaletteSource implements CommandPaletteSource
{
    public const string GROUP_NAVIGATION = NavigationPaletteSource::GROUP;
    public const string GROUP_COMMANDS = PanelCommandsPaletteSource::GROUP;

    public function __construct(
        private CrudRegistry $registry,
        private CrudAccess $access,
    ) {
    }

    public function entries(): iterable
    {
        foreach ($this->registry->declared() as $crud) {
            $keywords = $crud->key('palette.keywords');

            if ($crud->inPalette(CrudOperation::List)) {
                yield new PaletteEntry(
                    key: $crud->key('list'),
                    group: self::GROUP_NAVIGATION,
                    name: $crud->key('title'),
                    icon: $crud->icon,
                    description: 'crud.palette.list',
                    keywords: $keywords,
                    routeName: $crud->route('list'),
                    scope: $crud->scope,
                    offered: $this->offered($crud->name, CrudOperation::List),
                );
            }

            // The page of one's own record (a profile): an entry, not a search.
            if ($crud->own && $crud->inPalette(CrudOperation::Read)) {
                yield new PaletteEntry(
                    key: $crud->key('show'),
                    group: self::GROUP_NAVIGATION,
                    name: $crud->key('title'),
                    icon: $crud->icon,
                    description: 'crud.palette.own',
                    keywords: $keywords,
                    routeName: $crud->ownPage(),
                    scope: $crud->scope,
                    offered: $this->offered($crud->name, CrudOperation::Read),
                );
            }

            if ($crud->inPalette(CrudOperation::Create)) {
                yield new PaletteEntry(
                    key: $crud->key('create'),
                    group: self::GROUP_COMMANDS,
                    name: $crud->key('new'),
                    icon: 'plus-lg',
                    description: 'crud.palette.create',
                    keywords: $keywords,
                    routeName: $crud->route('new'),
                    command: true,
                    scope: $crud->scope,
                    offered: $this->offered($crud->name, CrudOperation::Create),
                );
            }
        }
    }

    /**
     * Whether the one asking is offered the operation of a resource, as the resource is in their request.
     *
     * @return Closure(): bool
     */
    private function offered(string $resource, CrudOperation $operation): Closure
    {
        return function () use ($resource, $operation): bool {
            $crud = $this->registry->get($resource);

            return $this->access->isGranted($crud) && $crud->inPalette($operation);
        };
    }
}
