<?php

declare(strict_types=1);

namespace Byfareska\Crud\Routing;

use Byfareska\Crud\Panel\Scope;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the panels out of the frames of other sites: every answer of a route of a panel (its pages, its live
 * requests, its files) says X-Frame-Options: SAMEORIGIN (byfareska_crud.frame_options) - a page of another site
 * cannot show the panel in a frame, transparent, under a button of its own, and have the one signed in click "delete"
 * or "approve" without seeing it (clickjacking).
 *
 * What the application says itself is kept: a response that already has an X-Frame-Options, or a Content-Security-
 * Policy with frame-ancestors (which browsers go by instead), is left as it is - also when a listener of the
 * application sets it later than this one would have (this one runs late, after those of the default priority).
 * Answers outside the routes of the panels (no "_scope"), the sign-in page of the application among them, are not
 * changed.
 */
#[AsEventListener(KernelEvents::RESPONSE, priority: -16)]
final readonly class PanelFrameOptions
{
    /** The value of the header, unless the configuration says otherwise. */
    public const string SAMEORIGIN = 'SAMEORIGIN';

    /**
     * @param string|false $value "SAMEORIGIN" (the pages of the same origin may frame the panel), "DENY" (none may);
     *     false = the header is not sent
     */
    public function __construct(
        #[Autowire('%byfareska_crud.frame_options%')]
        private string|false $value = self::SAMEORIGIN,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $scope = $event->getRequest()->attributes->get(Scope::ATTRIBUTE);
        if (
            $this->value === false
            || $this->value === ''
            || !$event->isMainRequest()
            || !is_string($scope)
            || $scope === ''
        ) {
            return;
        }

        $headers = $event->getResponse()->headers;
        if ($headers->has('X-Frame-Options')) {
            return;
        }
        foreach ($headers->all('content-security-policy') as $policy) {
            if (is_string($policy) && stripos($policy, 'frame-ancestors') !== false) {
                return;
            }
        }

        $headers->set('X-Frame-Options', $this->value);
    }
}
