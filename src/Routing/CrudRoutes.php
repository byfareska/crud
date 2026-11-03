<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Controller\CrudPageController;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\Resource\CrudRegistry;
use LogicException;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The pages of the #[AsCrud] resources of one route scope, added to its routes by {@see CrudRouteLoader}.
 */
final readonly class CrudRoutes
{
    /**
     * @param array{new?: string, edit?: string, page?: string} $paths the words of the addresses
     *     (byfareska_crud.paths)
     * @param array<string, array<string, mixed>> $scopes the configuration of the panels (byfareska_crud.scopes): the
     *     words of a panel of its own ("paths")
     */
    public function __construct(
        private CrudRegistry $registry,
        #[Autowire(param: 'byfareska_crud.paths')]
        private array $paths = [],
        #[Autowire(param: 'byfareska_crud.scopes')]
        private array $scopes = [],
    ) {
    }

    public function of(string $scope): RouteCollection
    {
        $routes = new RouteCollection();
        // A changed declaration may add or change routes. A new one is a new service, which rebuilds the container
        // and, with it, the routes.
        foreach ($this->registry->files() as $file) {
            $routes->addResource(new FileResource($file));
        }

        // As declared: the routes are the same for everybody, whatever the extensions make of a resource in a request.
        foreach ($this->registry->declared() as $crud) {
            if ($crud->scope !== $scope) {
                continue;
            }
            // One's own record: its details at the path, its form under it, no id in the address.
            if ($crud->own) {
                if ($crud->offers(CrudOperation::Read)) {
                    $this->add($routes, $crud->route('show'), $this->route($crud->path, $crud, 'show'));
                }
                if ($crud->offers(CrudOperation::Update)) {
                    $this->add(
                        $routes,
                        $crud->route('edit'),
                        $this->route($crud->path . '/' . $this->word('edit', $crud->scope), $crud, 'form'),
                    );
                }
                $this->addPages($routes, $crud);

                continue;
            }
            // One route for every page of the list: /users and /users/page/3. Both parts are optional
            // and left out for the first page; "_pages" only ever is the word "page". It has to be a single route:
            // the page number is a LiveProp mapped to the path, and that is written back into the route the list
            // was opened with - see CrudList::$page.
            // A resource listed in another one's list has no list page: its address is a 404.
            if ($crud->isListed()) {
                $list = $this->route($crud->path . '/{_pages}/{page}', $crud, 'list');
                $list->addDefaults(['_pages' => $this->word('page', $crud->scope), 'page' => 1]);
                $list->addRequirements([
                    '_pages' => preg_quote($this->word('page', $crud->scope), '#'),
                    'page' => '[1-9]\d*',
                ]);
                $this->add($routes, $crud->route('list'), $list);
            }
            // Pages of the whole resource, before the details page too, which would take their names for ids.
            $this->addCollectionPages($routes, $crud);
            // Before the details page, which would otherwise take "new" for an id.
            if ($crud->offers(CrudOperation::Create)) {
                $this->add(
                    $routes,
                    $crud->route('new'),
                    $this->route($crud->path . '/' . $this->word('new', $crud->scope), $crud, 'form'),
                );
            }
            if ($crud->offers(CrudOperation::Read)) {
                $this->add(
                    $routes,
                    $crud->route('show'),
                    $this->withId($crud, $this->route($crud->path . '/{id}', $crud, 'show')),
                );
            }
            if ($crud->offers(CrudOperation::Update)) {
                $this->add(
                    $routes,
                    $crud->route('edit'),
                    $this->withId(
                        $crud,
                        $this->route(
                            $crud->path . '/{id}/' . $this->word('edit', $crud->scope),
                            $crud,
                            'form',
                        ),
                    ),
                );
            }
            $this->addPages($routes, $crud);
        }

        return $routes;
    }

    /**
     * The pages #[AsCrudPage] adds to the records: <path>/{id}/<name>, or <path>/<name> for one's own record. The
     * name of the page is a default of the route, looked up among the pages of the resource by the controller.
     */
    private function addPages(RouteCollection $routes, AsCrud $crud): void
    {
        foreach ($this->registry->pagesOf($crud->name) as $page) {
            if ($page->collection) {
                continue;
            }
            // <path>/{id}/edit is the edit form: a page of that name would never be reached.
            $this->assertNoWord($crud, $page->name, ['edit']);
            $route = $this->route($crud->path . ($crud->own ? '' : '/{id}') . '/' . $page->name, $crud, 'page');
            $route->addDefaults(['name' => $page->name]);
            $this->withId($crud, $route);
            $this->add($routes, $crud->route($page->name), $route);
        }
    }

    /**
     * The pages #[AsCrudPage(collection: true)] adds to the whole resource: <path>/<name>.
     */
    private function addCollectionPages(RouteCollection $routes, AsCrud $crud): void
    {
        foreach ($this->registry->pagesOf($crud->name) as $page) {
            if (!$page->collection || $crud->own) {
                continue;
            }
            // <path>/page/3 is the list, <path>/new the add form: a page of either name would be taken for them.
            $this->assertNoWord($crud, $page->name, ['page', 'new']);
            $route = $this->route($crud->path . '/' . $page->name, $crud, 'page');
            $route->addDefaults(['name' => $page->name]);
            $this->add($routes, $crud->route($page->name), $route);
        }
    }

    /**
     * A page of #[AsCrudPage] is named by none of the words of the addresses of the bundle's pages there.
     *
     * @param list<string> $pages of byfareska_crud.paths
     *
     * @throws LogicException
     */
    private function assertNoWord(AsCrud $crud, string $name, array $pages): void
    {
        foreach ($pages as $page) {
            if ($name === $this->word($page, $crud->scope)) {
                throw new LogicException(sprintf(
                    'The page "%s" of "%s" has the address of its "%s" page (byfareska_crud.paths.%s: "%s"), which '
                    . 'would take its place: name it otherwise.',
                    $name,
                    $crud->name,
                    $page,
                    $page,
                    $this->word($page, $crud->scope),
                ));
            }
        }
    }

    /**
     * The word of an address of the pages of a resource: of its panel (byfareska_crud.scopes.*.paths), else
     * byfareska_crud.paths - "new", "edit", "page".
     */
    private function word(string $page, ?string $scope = null): string
    {
        $panel = $scope !== null ? $this->scopes[$scope]['paths'] ?? null : null;
        $own = is_array($panel) ? $panel[$page] ?? null : null;

        return is_string($own) && $own !== '' ? $own : $this->paths[$page] ?? $page;
    }

    /**
     * What the id of the address is made of, when the resource says (AsCrud::$idPattern): "\d+" leaves every other
     * address to the routes after it.
     */
    private function withId(AsCrud $crud, Route $route): Route
    {
        if ($crud->idPattern !== null && str_contains($route->getPath(), '{id}')) {
            $route->addRequirements(['id' => $crud->idPattern]);
        }

        return $route;
    }

    private function add(RouteCollection $routes, string $name, Route $route): void
    {
        if ($routes->get($name) instanceof Route) {
            throw new LogicException(sprintf('Two pages of the CRUD resources have the route "%s".', $name));
        }
        $routes->add($name, $route);
    }

    /**
     * @param string $action the method of CrudPageController: "list", "show", "form" or "page"
     */
    private function route(string $path, AsCrud $crud, string $action): Route
    {
        $route = new Route(
            $path,
            ['_controller' => CrudPageController::class . '::' . $action, 'crud' => $crud->name],
            methods: ['GET'],
        );
        // What the parameters of the application in its path are made of (AsCrud::$requirements).
        foreach ($crud->requirements as $parameter => $pattern) {
            if (str_contains($path, '{' . $parameter . '}')) {
                $route->setRequirement($parameter, $pattern);
            }
        }

        return $route;
    }
}
