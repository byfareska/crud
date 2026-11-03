<?php

declare(strict_types=1);

namespace Byfareska\Crud\Security;

/**
 * Nobody signed in: the {@see PanelAccount} of an application without Symfony Security.
 */
final readonly class NoPanelAccount implements PanelAccount
{
    public function user(): ?object
    {
        return null;
    }

    public function name(): ?string
    {
        return null;
    }

    public function identifier(): ?string
    {
        return null;
    }

    public function logoutUrl(): ?string
    {
        return null;
    }

    public function impersonationExitUrl(): ?string
    {
        return null;
    }
}
