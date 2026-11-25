<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Byfareska\Crud\Panel\ComponentPanel;
use Byfareska\Crud\Panel\Scope;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;

/**
 * The addresses LiveComponent makes for its components (data-live-url-value), given to it in place of the router:
 * the live route of a panel ({@see Scope::liveRoute()}, under its prefix) instead of the global "/_components/..." of
 * LiveComponent - of the panel of the component ({@see ComponentPanel}: a list, a form, a widget of a dashboard), else
 * of the page it is drawn on (and of the live requests of its components). A panel on a firewall of its own
 * (^/partner, another user class) then has its live requests in that firewall and its access_control: the one signed
 * in there is who re-renders the list and saves the form - and LivePanelGuard answers a component of a panel nowhere
 * else. For a panel whose routes are not imported (no live route of its own), and for a component with a route of its
 * own (#[AsLiveComponent(route: ...)]), the address is LiveComponent's.
 */
final class PanelLiveUrls implements UrlGeneratorInterface, ResetInterface
{
    /** The route of LiveComponent's endpoint (config/routes/ux_live_component.yaml). */
    public const string LIVE_COMPONENT_ROUTE = 'ux_live_component';

    /**
     * The component being drawn - its name and its panel - which LiveComponent makes the address of right after
     * (AddLiveAttributesSubscriber, on the same event).
     *
     * @var array{string, string}|null
     */
    private ?array $rendering = null;

    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requestStack,
        private readonly ?ComponentPanel $panels = null,
    ) {
    }

    /** Before LiveComponent makes the attributes of the component (priority 0). */
    #[AsEventListener(priority: 1)]
    public function onPreRender(PreRenderEvent $event): void
    {
        $panel = $event->getMetadata()->get('live', false) ? $this->panels?->of($event->getComponent()) : null;
        $this->rendering = $panel === null ? null : [$event->getMetadata()->getName(), $panel];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(
        string $name,
        array $parameters = [],
        int $referenceType = self::ABSOLUTE_PATH,
    ): string {
        if ($name !== self::LIVE_COMPONENT_ROUTE) {
            return $this->router->generate($name, $parameters, $referenceType);
        }

        $rendering = $this->rendering;
        $this->rendering = null;
        $scope = $rendering !== null && $rendering[0] === ($parameters['_live_component'] ?? null)
            ? $rendering[1]
            : $this->requestStack->getMainRequest()?->attributes->get(Scope::ATTRIBUTE);
        if (is_string($scope) && $scope !== '') {
            try {
                return $this->router->generate(Scope::liveRouteOf($scope), $parameters, $referenceType);
            } catch (RouteNotFoundException) {
                // The panel's routes are not imported this way: LiveComponent's endpoint.
            } catch (ExceptionInterface) {
                // The page has not the parameters of the prefix of that panel (a page of another panel, of the
                // application's): LiveComponent's endpoint, where LivePanelGuard refuses the component of a panel
                // with a live route of its own - it is to be drawn where its panel's prefix is known.
            }
        }

        return $this->router->generate($name, $parameters, $referenceType);
    }

    /**
     * Whether the panel has its own live route (its routes imported by a "byfareska_crud" route import): its
     * components are then answered only under its prefix.
     */
    public function hasLiveRoute(string $scope): bool
    {
        try {
            $this->router->generate(Scope::liveRouteOf($scope), ['_live_component' => 'x']);
        } catch (RouteNotFoundException) {
            return false;
        } catch (ExceptionInterface) {
            // There, but this request does not know the parameters of its prefix.
        }

        return true;
    }

    public function setContext(RequestContext $context): void
    {
        $this->router->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    public function reset(): void
    {
        $this->rendering = null;
    }
}
