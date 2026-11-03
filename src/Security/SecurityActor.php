<?php

declare(strict_types=1);

namespace Byfareska\Crud\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Byfareska\Crud\Resource\IdMapper;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The {@see Actor} of Symfony Security. Which value is the id of the user is told by the {@see UserIdMapper}, its text
 * by the {@see IdMapper} - as for the ids of records. Without the security bundle nobody is signed in and nobody has
 * a role: what declares one (a resource, a panel, a widget, an action) is for nobody - a role nobody can check is
 * not taken for "everybody". What declares none is for everybody; alias Actor to grant roles another way.
 */
final readonly class SecurityActor implements Actor
{
    public function __construct(
        private UserIdMapper $userIds,
        private IdMapper $ids,
        private ?Security $security = null,
    ) {
    }

    public function id(): ?string
    {
        $user = $this->security?->getUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        return $this->ids->toId($this->userIds->idOf($user))
            ?? throw new LogicException(sprintf(
                'The UserIdMapper gave no id for the user "%s".',
                $user->getUserIdentifier(),
            ));
    }

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        return $this->security instanceof Security && $this->security->isGranted($attribute, $subject);
    }
}
