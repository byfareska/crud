<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Routing\CrudRouteLoader;
use Byfareska\Crud\Tests\Functional\App\Entity\Contract;
use Dom\Element;
use Dom\HTMLDocument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * What changes something in a panel is answered only to a page of the same origin: a browser saying the request comes
 * from another site (or another subdomain) is refused, whatever headers that site got the browser to send; the live
 * route takes no HEAD, which LiveComponent would answer by hydrating the component from the address.
 */
final class SameOriginTest extends PanelTestCase
{
    public function testALiveActionFromAnotherSiteIsRefused(): void
    {
        $this->boot();
        $contract = (string) $this->seed()->id;
        $props = $this->props();

        foreach (['cross-site', 'same-site', 'none'] as $site) {
            $response = $this->live('POST', '/_crud/Crud:List/delete', $props, ['id' => $contract], $site);
            self::assertSame(403, $response->getStatusCode(), $site);
            self::assertSame(1, $this->entities()->getRepository(Contract::class)->count(), $site);
        }

        self::assertSame(200, $this->live('POST', '/_crud/Crud:List/delete', $props, ['id' => $contract], 'same-origin')
            ->getStatusCode());
        self::assertSame(0, $this->entities()->getRepository(Contract::class)->count());
    }

    public function testARequestOfABrowserNotSayingWhereItComesFromIsLeftToTheRest(): void
    {
        $this->boot();
        $this->seed();

        self::assertSame(200, $this->live('POST', '/_crud/Crud:List', $this->props())->getStatusCode());
    }

    public function testTheLiveRouteTakesNoHead(): void
    {
        $this->boot();
        $this->seed();
        $props = $this->props();

        self::assertSame(405, $this->live('HEAD', '/_crud/Crud:List', $props)->getStatusCode());
        self::assertSame(405, $this->live('PUT', '/_crud/Crud:List', $props)->getStatusCode());
    }

    public function testWhatChangesADashboardIsOnlyForThePageItself(): void
    {
        $router = $this->boot()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $guarded = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            if (str_starts_with($route->getPath(), '/_dashboard') || $route->getPath() === '/') {
                $guarded[$name] = $route->getDefault(CrudRouteLoader::SAME_ORIGIN) === true;
            }
        }

        self::assertContains(true, $guarded);
        foreach ($guarded as $name => $isGuarded) {
            $route = $router->getRouteCollection()->get($name);
            self::assertNotNull($route);
            self::assertSame($route->getMethods() !== ['GET'], $isGuarded, $name);
        }
    }

    private function props(): string
    {
        $document = HTMLDocument::createFromString($this->request('/contracts'), LIBXML_NOERROR);
        $element = $document->querySelector('[data-live-name-value="Crud:List"]');
        self::assertInstanceOf(Element::class, $element);

        return (string) $element->getAttribute('data-live-props-value');
    }

    /**
     * A live request as LiveComponent's script sends it, from the page the browser says (Sec-Fetch-Site; null = a
     * browser that does not say). A GET and a HEAD carry the props in the address.
     *
     * @param array<string, string> $args
     */
    private function live(
        string $method,
        string $uri,
        string $props,
        array $args = [],
        ?string $site = null,
    ): Response {
        $request = $method === 'POST'
            ? Request::create($uri, 'POST', ['data' => json_encode([
                'props' => json_decode($props, true),
                'args' => $args,
            ], JSON_THROW_ON_ERROR)])
            : Request::create($uri . '?' . http_build_query(['props' => $props, 'updated' => '{}']), $method);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/vnd.live-component+html');
        $request->headers->set('X-Live-Url', '/contracts');
        if ($site !== null) {
            $request->headers->set('Sec-Fetch-Site', $site);
        }

        return $this->handle($request);
    }
}
