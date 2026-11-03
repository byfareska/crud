<?php

declare(strict_types=1);

namespace Byfareska\Crud\Security;

use InvalidArgumentException;
use Stringable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * The {@see PanelAccount} of Symfony Security: the user of the token. A firewall without "logout" (a single sign-on,
 * a token in a header) has no way to sign out, and the menu offers none - rather than every page failing to make
 * its address. The address of signing out carries the CSRF token of the firewall's own "logout" settings.
 */
final readonly class SecurityPanelAccount implements PanelAccount
{
    public function __construct(
        private Security $security,
        #[Autowire(service: 'security.logout_url_generator')]
        private ?LogoutUrlGenerator $logoutUrls = null,
        #[Autowire(service: 'security.impersonate_url_generator')]
        private ?ImpersonateUrlGenerator $impersonation = null,
    ) {
    }

    public function user(): ?object
    {
        return $this->security->getUser();
    }

    public function name(): ?string
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        return $user instanceof Stringable ? (string) $user : $user->getUserIdentifier();
    }

    public function identifier(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    public function logoutUrl(): ?string
    {
        if (!$this->security->getUser() instanceof UserInterface || !$this->logoutUrls instanceof LogoutUrlGenerator) {
            return null;
        }

        try {
            return $this->logoutUrls->getLogoutPath();
        } catch (InvalidArgumentException) {
            // No "logout" on the firewall of the request.
            return null;
        }
    }

    public function impersonationExitUrl(): ?string
    {
        if (
            !$this->impersonation instanceof ImpersonateUrlGenerator
            || !$this->security->isGranted('IS_IMPERSONATOR')
        ) {
            return null;
        }

        // Empty when the firewall has no "switch_user".
        $exit = $this->impersonation->generateExitPath();

        return $exit === '' ? null : $exit;
    }
}
