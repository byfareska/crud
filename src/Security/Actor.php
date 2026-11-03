<?php

declare(strict_types=1);

namespace Byfareska\Crud\Security;

/**
 * Who is looking at the panel: what the menus, the palette, the dashboards and the CRUD pages ask before they offer
 * something. The bundle's own is {@see SecurityActor} (Symfony Security); an application with an idea of its own of
 * who acts aliases this interface to its service.
 */
interface Actor
{
    /**
     * The one signed in, as text (the way an address carries it); null = nobody. What "own" resources address and
     * what a dashboard layout is kept for.
     */
    public function id(): ?string;

    public function isGranted(string $attribute, mixed $subject = null): bool;
}
