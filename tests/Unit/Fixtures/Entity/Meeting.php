<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A meeting of people: a relation of many, kept to the scopes of the people.
 */
#[ORM\Entity]
class Meeting
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $topic = '';

    /** @var Collection<int, Person> */
    #[ORM\ManyToMany(targetEntity: Person::class)]
    public Collection $attendees;

    public function __construct()
    {
        $this->attendees = new ArrayCollection();
    }
}
