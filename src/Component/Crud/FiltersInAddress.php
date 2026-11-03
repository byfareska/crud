<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Crud;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\UX\TwigComponent\MountedComponent;

/**
 * Leaves out of the address of a list (X-Live-Url, written by the live component after each render) the filters that
 * are not in use and the empty parts of the others: the live component puts there the whole `filters`, which the
 * list fills with every field (CrudList::completeFilters()) - ?filters[query]=&filters[category][comparison]=eq&...
 * Runs after the live component wrote the header.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -8)]
final readonly class FiltersInAddress
{
    private const string HEADER = 'X-Live-Url';

    /** The query parameter of CrudList::$filters. */
    private const string PARAMETER = 'filters';

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $mounted = $event->getRequest()->attributes->get('_mounted_component');
        $url = $response->headers->get(self::HEADER);
        if (
            !$event->isMainRequest()
            || !$mounted instanceof MountedComponent
            || !($list = $mounted->getComponent()) instanceof CrudList
            || $url === null
            || !str_contains($url, '?')
        ) {
            return;
        }

        [$path, $query] = explode('?', $url, 2);
        parse_str($query, $parameters);
        $filters = $list->addressFilters();
        if ($filters === []) {
            unset($parameters[self::PARAMETER]);
        } else {
            $parameters[self::PARAMETER] = $filters;
        }

        $query = http_build_query($parameters);
        $response->headers->set(self::HEADER, $path . ($query !== '' ? '?' . $query : ''));
    }
}
