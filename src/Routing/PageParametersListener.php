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
 * context, so what is generated has them: the page's own route, or - for a live request of one of its components,
 * whose address (/_components/...) has none - the route of the page it comes from (the header X-Live-Url, which
 * LiveComponent sends).
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
        // A live request of the panel's own route has the parameters of its prefix, not those of the page.
        if ($request->attributes->has(Scope::ATTRIBUTE) && !$request->attributes->getBoolean(CrudRouteLoader::LIVE)) {
            $parameters = $request->attributes->get('_route_params');

            return is_array($parameters) ? $parameters : null;
        }

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

        // Only a page of a panel says what its parameters are.
        return isset($matched[Scope::ATTRIBUTE]) ? $matched : null;
    }
}
