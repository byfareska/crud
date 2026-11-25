<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Panel\Scope;
use Byfareska\Crud\Routing\PanelLiveUrls;
use Byfareska\Crud\Routing\PanelRoutesCheck;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * Every address of a panel is under the prefix of its route import, and what the import puts before them is checked
 * once the routes of the application are loaded.
 */
final class PanelRoutesTest extends PanelTestCase
{
    public function testEveryRouteOfAPanelIsUnderThePrefixOfItsImport(): void
    {
        $router = $this->boot()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $partner = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            if ($route->getDefault(Scope::ATTRIBUTE) === 'partner') {
                $partner[$name] = $route->getPath();
            }
        }

        self::assertArrayHasKey(Scope::liveRouteOf('partner'), $partner);
        // LiveComponent 2.36.3 / 3.8.1 and later answer a component without a route of its own only at its route.
        self::assertSame(
            PanelLiveUrls::LIVE_COMPONENT_ROUTE,
            $router->getRouteCollection()->get(Scope::liveRouteOf('partner'))?->getDefault('_canonical_route'),
        );
        self::assertArrayHasKey(Scope::firstListRoute('partner'), $partner);
        foreach ($partner as $name => $path) {
            self::assertStringStartsWith('/{shop}/partner', $path, $name);
        }
    }

    public function testAPageOfAPanelIsNotShownInAFrameOfAnotherSite(): void
    {
        $this->boot();
        $this->seed();

        $response = $this->handle(Request::create('/customers'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    public function testTheRoutesOfTheApplicationAreCheckedOnceLoaded(): void
    {
        self::assertInstanceOf(PanelRoutesCheck::class, $this->boot()->get('routing.loader'));
    }
}
