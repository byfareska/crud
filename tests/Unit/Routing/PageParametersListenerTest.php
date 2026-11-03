<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Routing;

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

    private function router(): Router
    {
        $routes = new RouteCollection();
        $routes->add('crud_product_list', new Route('/products', ['crud' => 'product', '_scope' => 'admin']));
        $routes->add('crud_product_show', new Route('/products/{id}', ['crud' => 'product', '_scope' => 'admin']));
        $routes->addPrefix('/{shop}/admin');
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
