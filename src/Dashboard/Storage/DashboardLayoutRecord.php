<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard\Storage;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A layout of a dashboard in the table "crud_dashboard_layout": the placements of one owner ("*" = everybody's, NULL
 * as an older version wrote it - see DoctrineDashboardLayouts) on
 * one dashboard, as JSON. Mapped by the bundle when byfareska_crud.dashboard.storage is "doctrine"; create the table
 * with a migration of the application (doctrine:migrations:diff).
 */
#[ORM\Entity]
#[ORM\Table(name: 'crud_dashboard_layout')]
#[ORM\UniqueConstraint(name: 'crud_dashboard_layout_owner_board', columns: ['owner', 'board'])]
class DashboardLayoutRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * @param list<array<string, mixed>> $placements
     */
    public function __construct(
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $owner,
        #[ORM\Column(length: 64)]
        private string $board,
        #[ORM\Column(type: 'json')]
        private array $placements,
    ) {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function getBoard(): string
    {
        return $this->board;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPlacements(): array
    {
        return $this->placements;
    }

    /**
     * @param list<array<string, mixed>> $placements
     */
    public function replacePlacements(array $placements): void
    {
        $this->placements = $placements;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
