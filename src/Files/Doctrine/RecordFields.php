<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files\Doctrine;

/**
 * The properties of the entity of a library Doctrine's library queries (byfareska_crud.files.entity_fields): those of
 * AbstractFileRecord by default. An entity of the application's with its own names says them: the reference in one
 * property ("reference": "public://ab/...") or in two ("storage" and "path").
 */
final readonly class RecordFields
{
    /**
     * @param string|null $reference a property holding the whole reference; null = "storage" and "path"
     * @param string|null $mimeType null = the library does not filter by the kind (a field of images lists every file)
     * @param string|null $created null = no order by the newest
     */
    public function __construct(
        public ?string $reference = null,
        public string $storage = 'storage',
        public string $path = 'path',
        public string $name = 'name',
        public ?string $mimeType = 'mimeType',
        public ?string $created = 'createdAt',
    ) {
    }

    /**
     * @param array{reference?: string|null, storage?: string, path?: string, name?: string, mime_type?: string|null,
     *     created?: string|null} $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            $config['reference'] ?? null,
            $config['storage'] ?? 'storage',
            $config['path'] ?? 'path',
            $config['name'] ?? 'name',
            array_key_exists('mime_type', $config) ? $config['mime_type'] : 'mimeType',
            array_key_exists('created', $config) ? $config['created'] : 'createdAt',
        );
    }
}
