<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Doctrine\Mapped;

use Byfareska\Crud\Files\FileRecord;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StoredFile;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * The columns of a file of a library kept by Doctrine: the bundle's entity (CrudFileRecord, table "crud_file") and an
 * entity of the application's extending it - with an "alt", an owner, a tenant - named in byfareska_crud.files.entity.
 * Such an entity gives itself a table, and an index unique on (storage, path). The names of the columns are fixed
 * (created_at, mime_type), whatever the naming strategy of the application.
 */
#[ORM\MappedSuperclass]
abstract class AbstractFileRecord implements FileRecord, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 64)]
    protected string $storage;

    #[ORM\Column(length: 255)]
    protected string $path;

    /** The name it was uploaded under. */
    #[ORM\Column(length: 255)]
    protected string $name;

    #[ORM\Column(name: 'mime_type', length: 127, nullable: true)]
    protected ?string $mimeType;

    #[ORM\Column(type: 'bigint', nullable: true)]
    protected int|string|null $size;

    #[ORM\Column(nullable: true)]
    protected ?int $width;

    #[ORM\Column(nullable: true)]
    protected ?int $height;

    #[ORM\Column(name: 'created_at')]
    protected DateTimeImmutable $createdAt;

    public function __construct(StoredFile $file)
    {
        $this->storage = $file->reference->storage;
        $this->path = $file->reference->path;
        $this->name = mb_substr($file->name, 0, 255);
        $this->mimeType = $file->mimeType;
        $this->size = $file->size;
        $this->width = $file->width;
        $this->height = $file->height;
        $this->createdAt = $file->createdAt ?? new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function reference(): FileReference
    {
        return new FileReference($this->storage, $this->path);
    }

    public function storedFile(): StoredFile
    {
        return new StoredFile(
            $this->reference(),
            $this->name,
            $this->mimeType,
            $this->size === null ? null : (int) $this->size,
            $this->width,
            $this->height,
            $this->createdAt,
        );
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
