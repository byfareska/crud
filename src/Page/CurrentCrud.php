<?php

declare(strict_types=1);

namespace Byfareska\Crud\Page;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The resource (and record) of the page being shown - of the main request - once its controller has opened it
 * ({@see CrudPages}); null on a page of no resource, and before the arguments of the controller are resolved: what
 * runs earlier (the firewall, access_control) does not know it.
 */
final readonly class CurrentCrud
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function get(): ?CrudContext
    {
        $context = $this->requestStack->getMainRequest()?->attributes->get(CrudContext::ATTRIBUTE);

        return $context instanceof CrudContext ? $context : null;
    }
}
