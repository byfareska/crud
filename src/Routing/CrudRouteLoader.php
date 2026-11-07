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
 */
#[AutoconfigureTag('routing.loader')]
final class CrudRouteLoader extends Loader
{
    public const string TYPE = 'byfareska_crud';

    /** The default of the live route of a scope: its requests are of the page they come from, not of their own. */
    public const string LIVE = '_crud_live';

    public function __construct(
        private readonly CrudRoutes $crudRoutes,
        private readonly Scopes $scopes,
        #[Autowire(param: 'byfareska_crud.files.enabled')]
        private readonly bool $files = false,
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
        }
        // More dashboards of the panel, each under a path of its own.
        foreach ($scope->boards as $board => $settings) {
            if ($board === $scope->name || $this->scopes->isConfigured($board)) {
                throw new InvalidArgumentException(sprintf(
                    'The board "%s" of the panel "%s" is named like a panel: name it otherwise.',
                    $board,
                    $scope->name,
                ));
            }
            $routes->addCollection($this->dashboard($board, rtrim($settings['path'], '/')));
        }
        $routes->addCollection($resources);
        if ($this->files) {
            $routes->add(FileRoutes::upload($scope->name), new Route(
                '/_files',
                ['_controller' => FilesController::class . '::upload'],
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
        // The live components of its pages, under its prefix: in the firewall of the panel (PanelLiveUrls).
        $routes->add($scope->liveRoute(), new Route(
            '/_crud/{_live_component}/{_live_action}',
            ['_live_action' => 'get', self::LIVE => true],
        ));
        $routes->addDefaults([Scope::ATTRIBUTE => $scope->name]);

        return $routes;
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
                ['_controller' => DashboardController::class . '::' . $controllerMethod, 'board' => $board],
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
