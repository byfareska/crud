<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Doctrine\Usage;

use Doctrine\ORM\Mapping as ORM;

/**
 * Where a file is used, in the table "crud_file_usage": the index of DoctrineFileUsages, mapped only when
 * byfareska_crud.files is enabled with the usages "doctrine". Create the table with a migration of the application.
 */
#[ORM\Entity]
#[ORM\Table(name: 'crud_file_usage')]
#[ORM\Index(name: 'crud_file_usage_reference', columns: ['reference'])]
#[ORM\Index(name: 'crud_file_usage_record', columns: ['resource', 'record_id'])]
class CrudFileUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    public function __construct(
        #[ORM\Column(length: 320)]
        private string $reference,
        #[ORM\Column(length: 64)]
        private string $resource,
        #[ORM\Column(name: 'record_id', length: 64)]
        private string $recordId,
        #[ORM\Column(length: 128)]
        private string $field,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getRecordId(): string
    {
        return $this->recordId;
    }

    public function getField(): string
    {
        return $this->field;
    }
}
