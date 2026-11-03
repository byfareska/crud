<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Byfareska\Crud\Panel\Scope;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * The addresses LiveComponent makes for its components (data-live-url-value), given to it in place of the router:
 * on a page of a panel - and in the live requests of its components - the live route of that panel
 * ({@see Scope::liveRoute()}, under its prefix) instead of the global "/_components/..." of LiveComponent. A panel on
 * a firewall of its own (^/partner, another user class) then has its live requests in that firewall: the one signed
 * in there is who re-renders the list and saves the form. Anywhere else, and for a component with a route of its own
 * (#[AsLiveComponent(route: ...)]), the address is LiveComponent's.
 */
final readonly class PanelLiveUrls implements UrlGeneratorInterface
{
    /** The route of LiveComponent's endpoint (config/routes/ux_live_component.yaml). */
    public const string LIVE_COMPONENT_ROUTE = 'ux_live_component';

    public function __construct(
        private UrlGeneratorInterface $router,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(
        string $name,
        array $parameters = [],
        int $referenceType = self::ABSOLUTE_PATH,
    ): string {
        $scope = $this->requestStack->getMainRequest()?->attributes->get(Scope::ATTRIBUTE);
        if ($name === self::LIVE_COMPONENT_ROUTE && is_string($scope) && $scope !== '') {
            try {
                return $this->router->generate('crud_live_' . $scope, $parameters, $referenceType);
            } catch (RouteNotFoundException) {
                // The panel's routes are not imported this way: LiveComponent's endpoint.
            }
        }

        return $this->router->generate($name, $parameters, $referenceType);
    }

    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }
}
