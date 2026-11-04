<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

/**
 * What byfareska_crud.files.storages.<name> says of a storage, besides the service of its bytes.
 */
final readonly class StorageSettings
{
    /**
     * @param string|null $publicUrl what the paths of the storage are under on the web
     *     ("https://cdn.example.com/media"), for UrlMode::Public; null = the storage's own (Flysystem's public_url)
     * @param int $ttl for how many seconds a temporary or signed address works
     * @param string|null $role who may upload to it and pick its files; null = whoever the panel is for
     * @param bool $upload whether files may be uploaded to it; false = only picked (an old directory, a DAM)
     * @param FileOffload|null $offload the web server sends its signed files in place of PHP; null = PHP does
     */
    public function __construct(
        public string $name,
        public UrlMode $url = UrlMode::Public,
        public ?string $publicUrl = null,
        public int $ttl = 3600,
        public ?string $role = null,
        public bool $upload = true,
        public ?FileOffload $offload = null,
    ) {
    }

    /**
     * @param array{
     *     url?: string,
     *     public_url?: string|null,
     *     ttl?: int,
     *     role?: string|null,
     *     upload?: bool,
     *     offload?: array{header: string, prefix: string}|null,
     * } $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        return new self(
            $name,
            UrlMode::from($config['url'] ?? UrlMode::Public->value),
            $config['public_url'] ?? null,
            $config['ttl'] ?? 3600,
            $config['role'] ?? null,
            $config['upload'] ?? true,
            isset($config['offload'])
                ? new FileOffload(OffloadHeader::from($config['offload']['header']), $config['offload']['prefix'])
                : null,
        );
    }
}
