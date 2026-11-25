<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Routing;

use Byfareska\Crud\Page\CurrentCrud;
use Byfareska\Crud\Panel\Scopes;
use Byfareska\Crud\Resource\CrudRegistry;
use Byfareska\Crud\Routing\CrudRouteLoader;
use Byfareska\Crud\Routing\CrudRoutes;
use Byfareska\Crud\Routing\PanelRoutesCheck;
use Byfareska\Crud\Tests\Unit\Fixtures\Gadget;
use Byfareska\Crud\Tests\Unit\Fixtures\RecordingActor;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingCrud;
use Byfareska\Crud\Tests\Unit\Fixtures\ThingHistoryPage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes of a panel are what their defaults say they are: the address chooses no resource, page, dashboard or
 * panel - whatever the import puts before them.
 */
final class PanelRoutesCheckTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function parametersOfTheApplication(): iterable
    {
        yield 'a shop' => ['/{shop}/admin', ''];
        yield 'the locale' => ['/{_locale}/admin', ''];
        yield 'a tenant of the host' => ['/admin', '{tenant}.example.com'];
    }

    #[DataProvider('parametersOfTheApplication')]
    public function testAParameterOfTheApplicationInThePrefixOrTheHostIsLeftToIt(string $prefix, string $host): void
    {
        $routes = $this->imported($prefix, $host);

        PanelRoutesCheck::check($routes);

        self::assertSame('thing', $routes->get('crud_thing_history')?->getDefault('crud'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function parametersOfTheBundle(): iterable
    {
        yield 'the page of #[AsCrudPage]' => ['/{name}/admin', '', 'name'];
        yield 'the resource' => ['/admin', '{crud}.example.com', 'crud'];
        yield 'the record' => ['/{id}/admin', '', 'id'];
        yield 'the dashboard' => ['/{board}/admin', '', 'board'];
        yield 'the panel' => ['/{_scope}/admin', '', '_scope'];
    }

    #[DataProvider('parametersOfTheBundle')]
    public function testAParameterOfTheBundleInThePrefixOrTheHostIsRefused(
        string $prefix,
        string $host,
        string $parameter,
    ): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has the parameter "{' . $parameter . '}" from the prefix or the host');

        PanelRoutesCheck::check($this->imported($prefix, $host));
    }

    public function testWithoutTheCheckTheAddressWouldChooseThePage(): void
    {
        // What the check is for: Symfony gives the parameter of the address precedence over the default of the route.
        $matched = new UrlMatcher($this->imported('/{name}/admin', ''), new RequestContext())
            ->match('/approve/admin/things/5/history');

        self::assertSame('crud_thing_history', $matched['_route']);
        self::assertSame('approve', $matched['name']);
    }

    public function testTheRoutesOfTheApplicationAreCheckedOnceLoaded(): void
    {
        $loader = new PanelRoutesCheck(new ClosureLoader());
        $loader->setResolver(new LoaderResolver([]));
        $routes = new RouteCollection();
        // A route of the application: its parameters are its own, whatever they are named.
        $routes->add('app_page', new Route('/{name}/{_scope}', ['_scope' => 'admin', 'name' => 'x']));

        self::assertSame($routes, $loader->load(static fn (): RouteCollection => $routes));

        $this->expectException(LogicException::class);
        $loader->load(fn (): RouteCollection => $this->imported('/{name}/admin', ''));
    }

    /**
     * The routes of the panel "panel" as a route import makes them: its prefix before their paths, its host.
     */
    private function imported(string $prefix, string $host): RouteCollection
    {
        $routes = $this->loader(['panel' => ['boards' => ['sales' => []]]])->load('panel');
        $routes->addPrefix($prefix);
        if ($host !== '') {
            $routes->setHost($host);
        }

        return $routes;
    }

    /**
     * @param array<string, array<string, mixed>> $config byfareska_crud.scopes
     */
    private function loader(array $config): CrudRouteLoader
    {
        $registry = new CrudRegistry([Gadget::class, ThingCrud::class], pages: [ThingHistoryPage::class]);
        $requests = new RequestStack();

        return new CrudRouteLoader(
            new CrudRoutes($registry, scopes: $config),
            // @phpstan-ignore argument.type (the configuration as the bundle has it, with its defaults)
            new Scopes($config, 'layout.html.twig', 'daisyui', [], $requests, new RecordingActor([]), new CurrentCrud(
                $requests,
            )),
        );
    }
}
