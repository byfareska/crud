<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Byfareska\Crud\Panel\ComponentPanel;
use Byfareska\Crud\Panel\Scope;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\MountedComponent;

/**
 * Answers a live request of a component of a panel ({@see ComponentPanel}: a list, a form, a widget of a dashboard)
 * only under the prefix of that panel - the live route of its route import ("/admin/_crud/..."), which PanelLiveUrls
 * gives it - so the firewall and the access_control of that prefix are what let it in, as they are for the pages:
 *
 * - under the live route of another panel ("/partner/_crud/Crud:List" with the signed props of a list of "admin"):
 *   403;
 * - at LiveComponent's own endpoint ("/_components/..."): 403 when its panel has a live route of its own; a panel
 *   whose routes are not imported has none and is answered there, as before;
 * - a component with a route of its own (#[AsLiveComponent(route: ...)]): only at that route.
 *
 * A component of no panel (one of the application's that does not implement ScopedComponent) is answered anywhere.
 * The signed props of a component are not bound to who, where or when (their checksum is of the props and the name
 * alone): without this, the props of a page of one panel would act under the prefix - the firewall, the
 * access_control - of another, or of none. Asked once the props are set and before the action or the render
 * (LiveComponent hydrates the component on kernel.controller, priority 10) - after the "onUpdated" hooks and the
 * #[PostHydrate] methods of the component, which therefore must not change anything that needs the panel; one that
 * does asks {@see self::assertCurrent()} first.
 */
final readonly class LivePanelGuard
{
    public function __construct(
        private ComponentPanel $panels,
        private PanelLiveUrls $urls,
        #[Autowire(service: 'ux.twig_component.component_factory')]
        private ComponentFactory $components,
        private RequestStack $requestStack,
    ) {
    }

    #[AsEventListener(KernelEvents::CONTROLLER, priority: 5)]
    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $mounted = $request->attributes->get('_mounted_component');
        if ($mounted instanceof MountedComponent && $request->attributes->has('_live_component')) {
            $this->assertServes($request, $mounted->getComponent(), $mounted->getName());
        }
    }

    /**
     * Refuses the live request being answered when it is not where the component may be answered; nothing outside a
     * live request. For what a component changes before kernel.controller (an "onUpdated" hook).
     *
     * @throws AccessDeniedHttpException
     */
    public function assertCurrent(object $component): void
    {
        $request = $this->requestStack->getMainRequest();
        $name = $request?->attributes->get('_component_name');
        if ($request instanceof Request && $request->attributes->has('_live_component') && is_string($name)) {
            $this->assertServes($request, $component, $name);
        }
    }

    /**
     * @throws AccessDeniedHttpException
     */
    private function assertServes(Request $request, object $component, string $name): void
    {
        $panel = $this->panels->of($component);
        if ($panel === null) {
            return;
        }

        // The live route of a panel: only its own components (and those of no panel).
        if ($request->attributes->getBoolean(CrudRouteLoader::LIVE)) {
            $scope = $request->attributes->get(Scope::ATTRIBUTE);
            if ($scope !== $panel) {
                throw new AccessDeniedHttpException(sprintf(
                    'The component "%s" is of the panel "%s": it is not answered under the prefix of "%s".',
                    $name,
                    $panel,
                    is_string($scope) ? $scope : '',
                ));
            }

            return;
        }

        // A route of its own: there only.
        $route = $this->components->metadataFor($name)->get('route', PanelLiveUrls::LIVE_COMPONENT_ROUTE);
        if ($route !== PanelLiveUrls::LIVE_COMPONENT_ROUTE) {
            if ($request->attributes->get('_route') !== $route) {
                throw new AccessDeniedHttpException(sprintf(
                    'The component "%s" is answered only at its route "%s".',
                    $name,
                    is_string($route) ? $route : '',
                ));
            }

            return;
        }

        // LiveComponent's endpoint: only for a panel without a live route of its own.
        if ($this->urls->hasLiveRoute($panel)) {
            throw new AccessDeniedHttpException(sprintf(
                'The component "%s" is of the panel "%s": it is answered only under the prefix of that panel ("%s").',
                $name,
                $panel,
                Scope::liveRouteOf($panel),
            ));
        }
    }
}
