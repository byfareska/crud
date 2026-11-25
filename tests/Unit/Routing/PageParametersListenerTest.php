<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Routing;

use Byfareska\Crud\Routing\CrudRouteLoader;
use Byfareska\Crud\Routing\PageParameters;
use Byfareska\Crud\Routing\PageParametersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

/**
 * A prefix of a route import with a parameter (/{shop}/admin): every address made on its pages has it.
 */
final class PageParametersListenerTest extends TestCase
{
    public function testThePageGivesItsParametersToWhatIsGenerated(): void
    {
        $router = $this->router();
        $parameters = new PageParameters();
        $request = Request::create('/acme/admin/products/7');
        // What RouterListener sets.
        $matched = $router->match('/acme/admin/products/7');
        $request->attributes->set('_scope', $matched['_scope']);
        $request->attributes->set('_route_params', $matched);

        new PageParametersListener($router, $parameters)($this->event($request));

        self::assertSame(['shop' => 'acme'], $parameters->all(), 'Not the bundle\'s own: the resource, the id.');
        self::assertSame('/acme/admin/products', $router->generate('crud_product_list'));
    }

    public function testALiveRequestHasThoseOfThePageItComesFrom(): void
    {
        $router = $this->router();
        $parameters = new PageParameters();
        $request = Request::create('/_components/Crud:List/save', 'POST');
        $request->headers->set('X-Live-Url', '/acme/admin/products?filters[query]=kubek');
        // What RouterListener does first.
        $router->getContext()->fromRequest($request);

        new PageParametersListener($router, $parameters)($this->event($request));

        self::assertSame('acme', $parameters->get('shop'));
        self::assertSame('/acme/admin/products/3', $router->generate('crud_product_show', ['id' => 3]));
        self::assertSame('POST', $router->getContext()->getMethod(), 'The context is that of the request again.');
    }

    public function testUnderTheLiveRouteOfAPanelItsPrefixSaysWhatItsParametersAre(): void
    {
        // What access_control, a voter on the route attribute "shop" see: "acme". The header says "globex".
        $parameters = $this->live('/acme/admin/_crud/Crud:List/delete', '/globex/admin/categories/5/products');

        self::assertSame(['shop' => 'acme'], $parameters->all(), 'Nothing of a page under another prefix.');
    }

    public function testUnderTheLiveRouteOfAPanelThePageAddsTheParametersOfItsPath(): void
    {
        $all = $this->live('/acme/admin/_crud/Crud:List', '/acme/admin/categories/5/products?page=2')->all();
        ksort($all);

        self::assertSame(['category' => '5', 'shop' => 'acme'], $all);
    }

    public function testUnderTheLiveRouteOfAPanelAPageOfAnotherPanelSaysNothing(): void
    {
        $parameters = $this->live('/acme/admin/_crud/Crud:List', '/acme/partner/regions/7/stores');

        self::assertSame(['shop' => 'acme'], $parameters->all());
    }

    public function testOnlyAPageOfAPanelSaysWhatItsParametersAre(): void
    {
        $router = $this->router();
        $parameters = new PageParameters();
        $request = Request::create('/_components/App:Other', 'POST');
        $request->headers->set('X-Live-Url', '/blog/acme');

        new PageParametersListener($router, $parameters)($this->event($request));

        self::assertSame([], $parameters->all());
        self::assertSame([], $router->getContext()->getParameters());
    }

    /**
     * A live request of the live route of the panel "admin" (as RouterListener leaves it), from a page.
     */
    private function live(string $uri, string $page): PageParameters
    {
        $router = $this->router();
        $parameters = new PageParameters();
        $request = Request::create($uri, 'POST');
        $request->headers->set('X-Live-Url', $page);
        $router->getContext()->fromRequest($request);
        // What RouterListener sets.
        $matched = [];
        foreach ($router->matchRequest($request) as $name => $value) {
            $request->attributes->set((string) $name, $value);
            if ($name !== '_route') {
                $matched[$name] = $value;
            }
        }
        $request->attributes->set('_route_params', $matched);

        new PageParametersListener($router, $parameters)($this->event($request));

        self::assertSame($parameters->all(), $router->getContext()->getParameters(), 'What is generated has them.');

        return $parameters;
    }

    private function router(): Router
    {
        $routes = new RouteCollection();
        $routes->add('crud_product_list', new Route('/products', ['crud' => 'product', '_scope' => 'admin']));
        $routes->add('crud_product_show', new Route('/products/{id}', ['crud' => 'product', '_scope' => 'admin']));
        $routes->add('crud_category_products', new Route(
            '/categories/{category}/products',
            ['crud' => 'product', '_scope' => 'admin'],
        ));
        $routes->add('crud_live_admin', new Route(
            '/_crud/{_live_component}/{_live_action}',
            ['_live_action' => 'get', '_scope' => 'admin', CrudRouteLoader::LIVE => true],
        ));
        $routes->addPrefix('/{shop}/admin');
        $partner = new RouteCollection();
        $partner->add('crud_region_stores', new Route(
            '/regions/{region}/stores',
            ['crud' => 'store', '_scope' => 'partner'],
        ));
        $partner->addPrefix('/{shop}/partner');
        $routes->addCollection($partner);
        $routes->add('blog', new Route('/blog/{shop}'));

        return new Router(new ClosureLoader(), static fn (): RouteCollection => $routes);
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent(
            new class implements HttpKernelInterface {
                public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): never
                {
                    throw new \LogicException('Not handled.');
                }
            },
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
