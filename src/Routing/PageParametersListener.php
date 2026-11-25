<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Byfareska\Crud\Panel\Scope;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Finds the parameters of the panel page a request is for ({@see PageParameters}) and gives them to the routing
 * context, so what is generated has them: the page's own route, or - for a live request of one of its components -
 * also the route of the page it comes from (the header X-Live-Url, which LiveComponent sends):
 *
 * - under the live route of a panel (/{shop}/admin/_crud/...) the parameters of its prefix are those of the address
 *   asked for - what the firewall, access_control and the listeners of the application went by - never those the
 *   header says; the header adds only those of the path of the page (/categories/{category}/products), and only
 *   when it is a page of the same panel under the same prefix, else nothing;
 * - at LiveComponent's own endpoint (/_components/..., which has none) the header is all there is - only for a
 *   component of no panel, or of a panel without a live route of its own (LivePanelGuard).
 */
#[AsEventListener(KernelEvents::REQUEST, priority: 16)]
final readonly class PageParametersListener
{
    /** The bundle's own parameters of its routes, never the application's. */
    public const array RESERVED = ['crud', 'id', 'page', 'name', 'board'];

    private const string LIVE_URL = 'X-Live-Url';

    public function __construct(
        private RouterInterface $router,
        private PageParameters $parameters,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $routeParameters = $this->routeParameters($event->getRequest());
        if ($routeParameters === null) {
            return;
        }

        $own = [];
        foreach ($routeParameters as $name => $value) {
            if (
                is_string($name)
                && !str_starts_with($name, '_')
                && !in_array($name, self::RESERVED, true)
                && is_scalar($value)
            ) {
                $own[$name] = (string) $value;
            }
        }

        $this->parameters->set($own);
        $context = $this->router->getContext();
        foreach ($own as $name => $value) {
            $context->setParameter($name, $value);
        }
    }

    /**
     * The parameters of the route of the panel page: of this request, or of the page a live request comes from; null
     * for anything else.
     *
     * @return array<mixed>|null
     */
    private function routeParameters(Request $request): ?array
    {
        $scope = $request->attributes->get(Scope::ATTRIBUTE);
        if ($scope === null) {
            // LiveComponent's endpoint: the page it comes from says it all.
            $page = $this->pageOf($request);

            return isset($page[Scope::ATTRIBUTE]) ? $page : null;
        }
        // What RouterListener matched (else the attributes it would have set them as).
        $own = $request->attributes->get('_route_params');
        $own = is_array($own) ? $own : $request->attributes->all();
        if (!$request->attributes->getBoolean(CrudRouteLoader::LIVE)) {
            return $own;
        }

        // The live route of a panel: its prefix says what its parameters are, the page adds those of its path - a page
        // of this panel, under this very prefix.
        $page = $this->pageOf($request);
        if ($page === null || ($page[Scope::ATTRIBUTE] ?? null) !== $scope) {
            return $own;
        }
        foreach ($own as $name => $value) {
            if (
                is_string($name)
                && !str_starts_with($name, '_')
                && array_key_exists($name, $page)
                && (!is_scalar($value) || !is_scalar($page[$name]) || (string) $value !== (string) $page[$name])
            ) {
                return $own;
            }
        }

        return [...$page, ...$own];
    }

    /**
     * The parameters of the route of the page a live request comes from (X-Live-Url); null = it names none.
     *
     * @return array<mixed>|null
     */
    private function pageOf(Request $request): ?array
    {
        $url = $request->headers->get(self::LIVE_URL);
        $path = is_string($url) ? parse_url($url, PHP_URL_PATH) : null;
        if (!is_string($path) || $path === '') {
            return null;
        }

        // The page was a GET; the live request is not.
        $context = $this->router->getContext();
        $page = clone $context;
        $page->setMethod('GET');
        $this->router->setContext($page);
        try {
            $matched = $this->router->match($path);
        } catch (ExceptionInterface) {
            return null;
        } finally {
            $this->router->setContext($context);
        }

        return $matched;
    }
}
