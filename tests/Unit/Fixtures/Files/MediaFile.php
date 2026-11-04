<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures\Files;

use Byfareska\Crud\Files\FileRecord;
use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\StoredFile;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A file of an application with names of its own: the whole reference in "url", the name in "title" - found by the
 * library through byfareska_crud.files.entity_fields.
 */
#[ORM\Entity]
#[ORM\Table(name: 'media_file')]
class MediaFile implements FileRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 320, unique: true)]
        public string $url,
        #[ORM\Column]
        public string $title,
        #[ORM\Column(nullable: true)]
        public ?string $kind,
        #[ORM\Column]
        public DateTimeImmutable $uploadedAt,
    ) {
    }

    public function storedFile(): StoredFile
    {
        $reference = FileReference::parse($this->url);

        return new StoredFile($reference, $this->title, $this->kind, createdAt: $this->uploadedAt);
    }
}
