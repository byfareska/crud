<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Byfareska\Crud\Controller\DashboardController;
use Byfareska\Crud\Controller\FilesController;
use Byfareska\Crud\Files\FileRoutes;
use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Panel\Scopes;
use InvalidArgumentException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The pages of one panel (route scope), imported with the type "byfareska_crud" and the scope as the resource:
 *
 *     admin:
 *         resource: admin
 *         type: byfareska_crud
 *         prefix: /admin
 *
 * The CRUD pages of the #[AsCrud] resources of the scope and, when the scope has one, its dashboard at "/" (with the
 * endpoints its grid saves through, under "/_dashboard"), and the endpoint of the live components drawn on them
 * ("/_crud/{component}/{action}"), so live requests are in the firewall of the panel too; with byfareska_crud.files,
 * the upload and the download of files and the sizes of images made when first asked for ("/_files",
 * "/_files/download", "/_files/variant"). Every route gets the default
 * "_scope", which the menu, the palette and the layout go by, and which access_control can go by: `attributes: {
 * _scope: admin }` - besides the role of the scope, which the bundle checks itself on every way to its resources.
 *
 * What the import says (prefix, host, schemes, requirements, condition) is said of every one of these routes, so a
 * panel is all under its prefix and on its host. Its pages know what they are of by the defaults of their routes alone
 * ("crud", "name", "board", "_scope"): a parameter of the prefix or the host named like one of them would let the
 * address choose it, and PanelRoutesCheck refuses the routes then.
 */
#[AutoconfigureTag('routing.loader')]
final class CrudRouteLoader extends Loader
{
    public const string TYPE = 'byfareska_crud';

    /** The alias of RelationAutocompleter, the searches of the relations of the forms and filters of a panel. */
    public const string AUTOCOMPLETE_ALIAS = 'byfareska_crud_relation';

    /** The default of the autocomplete route of a scope: RelationAutocompleter answers only through it. */
    public const string AUTOCOMPLETE = '_crud_autocomplete';

    /** The default of the live route of a scope: its requests are of the page they come from, not of their own. */
    public const string LIVE = '_crud_live';

    /**
     * The default of the routes of a panel that change something - its live route, the upload of files, the layout of
     * a dashboard: answered only to a page of the same origin ({@see SameOriginGuard}).
     */
    public const string SAME_ORIGIN = '_crud_same_origin';

    /**
     * The option of every route of a panel: the parameters of the route as the bundle made it, before the route import
     * put its prefix and host before it - what {@see PanelRoutesCheck} tells the parameters of the import from.
     */
    public const string VARIABLES = 'byfareska_crud_variables';

    public function __construct(
        private readonly CrudRoutes $crudRoutes,
        private readonly Scopes $scopes,
        #[Autowire(param: 'byfareska_crud.files.enabled')]
        private readonly bool $files = false,
        #[Autowire(param: 'byfareska_crud.autocomplete')]
        private readonly bool $autocomplete = false,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if (!is_string($resource)) {
            throw new InvalidArgumentException(sprintf(
                'The resource of a "%s" route import is the name of a scope, %s given.',
                self::TYPE,
                get_debug_type($resource),
            ));
        }
        $scope = $this->scopes->declared($resource);
        $routes = new RouteCollection();
        $resources = $this->crudRoutes->of($scope->name);
        // A panel nobody configured and no resource names: a mistake in the name, not a panel without a role.
        if (!$this->scopes->isConfigured($scope->name) && $resources->count() === 0) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" route import names the panel "%s", which byfareska_crud.scopes does not configure and no '
                . 'resource is in.',
                self::TYPE,
                $scope->name,
            ));
        }

        if ($scope->dashboard) {
            $routes->addCollection($this->dashboard($scope->name, ''));
        } elseif ($scope->home === null) {
            // No dashboard: "/" of the panel is its first list, so the brand and the panel switch lead somewhere
            // (RedirectController: 404 for a panel without a list).
            $routes->add(Scope::firstListRoute($scope->name), new Route('/', [
                '_controller' => RedirectController::class . '::redirectAction',
                'route' => $this->firstList($resources) ?? '',
                'permanent' => false,
            ], methods: ['GET']));
        }
        // More dashboards of the panel, each under a path of its own.
        foreach ($scope->boards as $board => $settings) {
            // Its routes, its layouts, its widgets and the panel its live requests are answered in go by its name.
            if (
                $board === $scope->name
                || $this->scopes->isConfigured($board)
                || $this->crudRoutes->of($board)->count() > 0
            ) {
                throw new InvalidArgumentException(sprintf(
                    'The board "%s" of the panel "%s" is named like a panel: name it otherwise.',
                    $board,
                    $scope->name,
                ));
            }
            if ($this->scopes->panelOf($board) !== $scope->name) {
                throw new InvalidArgumentException(sprintf(
                    'The board "%s" of the panel "%s" is a board of the panel "%s" too: name it otherwise.',
                    $board,
                    $scope->name,
                    $this->scopes->panelOf($board),
                ));
            }
            $routes->addCollection($this->dashboard($board, rtrim($settings['path'], '/')));
        }
        $routes->addCollection($resources);
        if ($this->files) {
            $routes->add(FileRoutes::upload($scope->name), new Route(
                '/_files',
                ['_controller' => FilesController::class . '::upload', self::SAME_ORIGIN => true],
                methods: ['POST'],
            ));
            $routes->add(FileRoutes::download($scope->name), new Route(
                '/_files/download',
                ['_controller' => FilesController::class . '::download'],
                methods: ['GET'],
            ));
            $routes->add(FileRoutes::variant($scope->name), new Route(
                '/_files/variant',
                ['_controller' => FilesController::class . '::variant'],
                methods: ['GET'],
            ));
        }
        // The searches of the relations of its forms and filters, under its prefix: in the firewall of the panel and
        // its access_control, not at the global /autocomplete of symfony/ux-autocomplete.
        if ($this->autocomplete) {
            $routes->add($scope->autocompleteRoute(), new Route(
                '/_crud/autocomplete/{alias}',
                ['_controller' => 'ux.autocomplete.autocomplete_controller', self::AUTOCOMPLETE => true],
                ['alias' => preg_quote(self::AUTOCOMPLETE_ALIAS, '#')],
                methods: ['GET'],
            ));
        }
        // The live components of its pages, under its prefix: in the firewall of the panel (PanelLiveUrls). Its own
        // components are answered only here (LivePanelGuard), to a page of the same origin (SameOriginGuard). It is
        // LiveComponent's own route under another address: "_canonical_route" says so to LiveComponent 2.36.3 / 3.8.1
        // and later, which answer a component without a route of its own only at the route "ux_live_component".
        $routes->add($scope->liveRoute(), new Route(
            '/_crud/{_live_component}/{_live_action}',
            [
                '_live_action' => 'get',
                '_canonical_route' => PanelLiveUrls::LIVE_COMPONENT_ROUTE,
                self::LIVE => true,
                self::SAME_ORIGIN => true,
            ],
        ));
        $routes->addDefaults([Scope::ATTRIBUTE => $scope->name]);
        foreach ($routes->all() as $route) {
            $route->setOption(self::VARIABLES, $route->compile()->getVariables());
        }

        return $routes;
    }

    /** The first list of the panel whose address needs no parameter of the application. */
    private function firstList(RouteCollection $resources): ?string
    {
        foreach ($resources->all() as $name => $route) {
            // The paging of a list ({_pages}/{page}) has defaults; any other parameter is the application's.
            if (
                str_ends_with($name, '_list')
                && array_all(
                    $route->compile()->getPathVariables(),
                    static fn (mixed $variable): bool => in_array($variable, ['_pages', 'page'], true),
                )
            ) {
                return $name;
            }
        }

        return null;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === self::TYPE;
    }

    /**
     * The dashboard of a board - the panel's own (under "/") or one of its boards (under its path).
     */
    private function dashboard(string $board, string $prefix): RouteCollection
    {
        $routes = new RouteCollection();
        $add = static function (
            string $action,
            string $path,
            string $method,
            string $controllerMethod,
        ) use (
            $routes,
            $board,
            $prefix,
): void {
            $routes->add(Scope::boardRoute($board, $action), new Route(
                $path === '/' && $prefix !== '' ? $prefix : $prefix . $path,
                // What changes the layout only from a page of the same origin (SameOriginGuard); the page from any.
                ['_controller' => DashboardController::class . '::' . $controllerMethod, 'board' => $board]
                    + ($method === 'GET' ? [] : [self::SAME_ORIGIN => true]),
                ['id' => '[A-Za-z0-9_-]+'],
                methods: [$method],
            ));
        };

        $add('', '/', 'GET', 'show');
        $add('save', '/_dashboard/layout', 'PUT', 'save');
        $add('add', '/_dashboard/widget', 'POST', 'add');
        $add('remove', '/_dashboard/widget/{id}', 'DELETE', 'remove');
        $add('reset', '/_dashboard/reset', 'POST', 'reset');
        $add('share', '/_dashboard/default', 'PUT', 'share');

        return $routes;
    }
}
