<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user of the application, as "my account" edits it: what they may change of themselves, what says what they may
 * do or are vouched for, and whose they are.
 */
#[ORM\Entity]
class Account implements UserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $email = '';

    #[ORM\Column]
    public string $displayName = '';

    /** @var list<string> */
    #[ORM\Column(type: 'simple_array', nullable: true)]
    public array $roles = [];

    #[ORM\Column]
    public bool $isVerified = false;

    #[ORM\Column]
    public bool $enabled = true;

    #[ORM\Column]
    public float $hourlyRate = 0.0;

    #[ORM\ManyToOne]
    public ?Team $team = null;

    /** Somebody above them: a user too. */
    #[ORM\ManyToOne]
    public ?Account $mentor = null;

    /** Neither what they may do nor whose they are. */
    #[ORM\ManyToOne]
    public ?Order $lastOrder = null;

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email !== '' ? $this->email : 'nobody';
    }
}
