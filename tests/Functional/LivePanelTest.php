<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Functional;

use Byfareska\Crud\Tests\Functional\App\Entity\Contract;
use Dom\Element;
use Dom\HTMLDocument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The live requests of a component of a panel are answered only under the prefix of that panel - where its firewall
 * and access_control are - whoever has the signed props of it; the parameters of that prefix are those of the address
 * asked for, not those of the page the browser says the request comes from.
 */
final class LivePanelTest extends PanelTestCase
{
    public function testTheListOfAPanelIsAddressedAndAnsweredUnderItsPrefix(): void
    {
        $this->boot();
        $this->seed();

        [$url, $props] = $this->component($this->request('/contracts'), 'Crud:List');

        self::assertSame('/_crud/Crud:List', $url);
        self::assertSame(200, $this->live('/_crud/Crud:List', $props)->getStatusCode());
    }

    public function testTheListOfAPanelIsRefusedUnderThePrefixOfAnother(): void
    {
        $this->boot();
        $this->seed();
        [, $props] = $this->component($this->request('/contracts'), 'Crud:List');

        self::assertSame(403, $this->live('/acme/partner/_crud/Crud:List', $props)->getStatusCode());
    }

    public function testTheListOfAPanelIsRefusedAtTheEndpointOfLiveComponent(): void
    {
        $this->boot();
        $this->seed();
        [, $props] = $this->component($this->request('/contracts'), 'Crud:List');

        self::assertSame(403, $this->live('/_components/Crud:List', $props)->getStatusCode());
    }

    public function testAnActionOfTheListIsDoneOnlyUnderThePrefixOfItsPanel(): void
    {
        $this->boot();
        $contract = (string) $this->seed()->id;
        [, $props] = $this->component($this->request('/contracts'), 'Crud:List');

        foreach (['/acme/partner/_crud/Crud:List/delete', '/_components/Crud:List/delete'] as $elsewhere) {
            self::assertSame(403, $this->live($elsewhere, $props, ['id' => $contract])->getStatusCode(), $elsewhere);
            self::assertSame(1, $this->entities()->getRepository(Contract::class)->count(), $elsewhere);
        }

        self::assertSame(200, $this->live('/_crud/Crud:List/delete', $props, ['id' => $contract])->getStatusCode());
        self::assertSame(0, $this->entities()->getRepository(Contract::class)->count());
    }

    public function testAComponentOfNoPanelIsAnsweredAnywhere(): void
    {
        $this->boot();
        [$url, $props] = $this->component($this->render('App:Greeting'), 'App:Greeting');

        self::assertSame('/_components/App:Greeting', $url, 'Drawn outside a panel.');
        foreach (['/_crud/App:Greeting', '/acme/partner/_crud/App:Greeting', '/_components/App:Greeting'] as $uri) {
            self::assertSame(200, $this->live($uri, $props)->getStatusCode(), $uri);
        }
    }

    public function testThePrefixOfTheLiveRouteSaysTheParametersNotThePageTheBrowserNames(): void
    {
        $this->boot();
        [, $props] = $this->component($this->render('App:Greeting'), 'App:Greeting');

        $shop = fn (string $uri, string $page): string => (string) $this->live($uri, $props, page: $page)->getContent();
        $partner = '/acme/partner/_crud/App:Greeting';

        // What access_control, a voter on the route attribute "shop" see is what the component goes by.
        self::assertStringContainsString('of the shop acme', $shop($partner, '/globex/partner'));
        self::assertStringContainsString('of the shop acme', $shop($partner, '/acme/partner'));
        // Not a page of this panel: nothing of it.
        self::assertStringContainsString('of the shop acme', $shop($partner, '/contracts'));
        // LiveComponent's endpoint has no prefix: the page it comes from is all there is.
        self::assertStringContainsString('of the shop globex', $shop('/_components/App:Greeting', '/globex/partner'));
    }

    /**
     * The address and the props of the first component of that name on a page.
     *
     * @return array{string, string}
     */
    private function component(string $html, string $name): array
    {
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $element = $document->querySelector(sprintf('[data-live-name-value="%s"]', $name));
        self::assertInstanceOf(Element::class, $element, $name . ' is not on the page.');

        return [
            (string) $element->getAttribute('data-live-url-value'),
            (string) $element->getAttribute('data-live-props-value'),
        ];
    }

    private function render(string $component): string
    {
        $twig = $this->container()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate(sprintf("{{ component('%s') }}", $component))->render();
    }

    /**
     * A live request as LiveComponent's script sends it: a re-render, or an action with its arguments.
     *
     * @param array<string, string> $args
     */
    private function live(string $uri, string $props, array $args = [], string $page = '/contracts'): Response
    {
        $request = Request::create($uri, 'POST', ['data' => json_encode([
            'props' => json_decode($props, true),
            'args' => $args,
        ], JSON_THROW_ON_ERROR)]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/vnd.live-component+html');
        $request->headers->set('X-Live-Url', $page);

        return $this->handle($request);
    }
}
