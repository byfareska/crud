<?php

declare(strict_types=1);

namespace Byfareska\Crud\Panel;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;

/**
 * The panel of a request of LiveComponent's own endpoint (/_components/<name>/..., not the live route of a panel,
 * which says it): that address has no route default "_scope",
 * so the component rendered ({@see ScopedComponent}) says it, before its templates are looked for in a theme. Only
 * there: on a page the panel is known before anything is drawn, and a component of another panel shown on it does not
 * change it halfway.
 */
#[AsEventListener]
final readonly class ScopeOfComponent
{
    /** The route parameter of the live endpoint naming the component (config/routes/ux_live_component.yaml). */
    private const string LIVE_COMPONENT = '_live_component';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(PreRenderEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();
        $component = $event->getComponent();
        if (
            !$request instanceof Request
            || !$request->attributes->has(self::LIVE_COMPONENT)
            || $request->attributes->has(Scope::ATTRIBUTE)
            || !$component instanceof ScopedComponent
        ) {
            return;
        }

        $scope = $component->panelScope();
        if ($scope !== null && $scope !== '') {
            $request->attributes->set(Scope::ATTRIBUTE, $scope);
        }
    }
}
