<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;

/**
 * A property of an entity or a DTO holding files: a form guessed from it (a DoctrineSource without a form type, a field
 * of #[FormField] without one) gets a field of files for it (CrudFileType) - with byfareska_crud.files enabled.
 *
 *     #[CrudFile(accept: ['images'])]
 *     #[ORM\Column(nullable: true)]
 *     public ?string $logo = null;
 *
 *     #[CrudFile(multiple: true, accept: ['images'], meta: ['alt'], class: ProductImage::class)]
 *     #[ORM\OneToMany(ProductImage::class, 'product', cascade: ['persist'], orphanRemoval: true)]
 *     #[ORM\OrderBy(['position' => 'ASC'])]
 *     private Collection $images;
 *
 * The binding (how the property holds the files, see CrudFileType) is told by its type when not given: a string or
 * an array is "column"; an object of a FileRecord, or a list of them ($class), is "record"; a list of other
 * objects ($class) is "items".
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CrudFile
{
    /**
     * @param string|null $storage where uploads go; null = byfareska_crud.files.default_storage
     * @param bool|null $multiple many files; null = when the property is a list
     * @param list<string> $accept the kinds taken: "images", "image/*", "application/pdf", ".pdf"
     * @param list<string> $meta what is said of each file: "alt", "caption"
     * @param string|null $binding "column", "record" or "items"; null = by the type of the property
     * @param class-string|null $class of the objects of a list: a FileRecord ("record"), or an item ("items")
     * @param array<string, mixed> $options more options of CrudFileType: "max_files", "browse", "item_position"...
     */
    public function __construct(
        public ?string $storage = null,
        public ?bool $multiple = null,
        public array $accept = [],
        public array $meta = [],
        public ?string $binding = null,
        public ?string $class = null,
        public array $options = [],
    ) {
    }
}
