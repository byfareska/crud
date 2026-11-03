<?php

declare(strict_types=1);

namespace Byfareska\Crud\Security;

/**
 * The one signed in, as the menu of the panel shows them: their name, the way to sign out, the way back to their own
 * account when they act as somebody else. The bundle's is {@see SecurityPanelAccount} (Symfony Security), without
 * SecurityBundle {@see NoPanelAccount} (nobody). Alias the interface to change it - a logout of a single sign-on, a
 * name read elsewhere, no way out of a kiosk.
 */
interface PanelAccount
{
    /** The user, for what the theme reads of it besides (an avatar); null = nobody signed in. */
    public function user(): ?object;

    /** What the menu calls them: __toString(), else their identifier; null = nobody signed in. */
    public function name(): ?string;

    /** Their identifier (an e-mail, a login); null = nobody signed in. */
    public function identifier(): ?string;

    /** Where signing out goes (a POST); null = there is no way to sign out here, and the menu offers none. */
    public function logoutUrl(): ?string;

    /** Where the one acting as this user goes back to their own account; null = nobody acts as somebody else. */
    public function impersonationExitUrl(): ?string;
}
