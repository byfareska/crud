<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;

/**
 * A property of an entity or a DTO holding HTML: a form guessed from it gets a field of rich text for it
 * (CrudRichTextType) - drawn by the editor of byfareska_crud.rich_text.
 *
 *     #[CrudRichText(files: ['accept' => ['images']])]
 *     #[ORM\Column(type: Types::TEXT, nullable: true)]
 *     public ?string $description = null;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class CrudRichText
{
    /**
     * @param string|null $editor the name of the editor; null = byfareska_crud.rich_text.editor
     * @param array<string, mixed> $editorOptions what the editor is given, after byfareska_crud.rich_text.options
     * @param bool|array<string, mixed>|null $files whether files are uploaded into the text, or where to (storage,
     *     accept, max_size, browse); null = byfareska_crud.rich_text.files
     * @param array<string, mixed> $options more options of CrudRichTextType: "attr", "help"...
     */
    public function __construct(
        public ?string $editor = null,
        public array $editorOptions = [],
        public bool|array|null $files = null,
        public array $options = [],
    ) {
    }
}
