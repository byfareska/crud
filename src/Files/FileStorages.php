<?php

declare(strict_types=1);

namespace Byfareska\Crud\Files;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;

/**
 * The storages of byfareska_crud.files.storages by name: the service of their bytes and what the configuration says
 * of them. The services are made when first asked for.
 */
final readonly class FileStorages
{
    /** @var array<string, StorageSettings> */
    private array $settings;

    /**
     * @param array<string, array{
     *     url?: string,
     *     public_url?: string|null,
     *     ttl?: int,
     *     role?: string|null,
     *     upload?: bool,
     * }> $settings
     * @param ContainerInterface $storages the FileStorage of each, by name
     * @param string|null $default where a field that names none uploads; null = the first
     */
    public function __construct(
        array $settings,
        private ContainerInterface $storages,
        private ?string $default = null,
    ) {
        $all = [];
        foreach ($settings as $name => $config) {
            $all[$name] = StorageSettings::fromConfig($name, $config);
        }
        $this->settings = $all;
    }

    public function has(string $name): bool
    {
        return isset($this->settings[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->settings);
    }

    /** The storage a field that names none uploads to. */
    public function default(): string
    {
        return $this->default ?? array_key_first($this->settings)
            ?? throw new InvalidArgumentException('byfareska_crud.files.storages names no storage.');
    }

    /**
     * @throws InvalidArgumentException no such storage
     */
    public function settings(string $name): StorageSettings
    {
        return $this->settings[$name] ?? throw $this->unknown($name);
    }

    /**
     * @throws InvalidArgumentException no such storage
     */
    public function storage(string $name): FileStorage
    {
        if (!$this->has($name)) {
            throw $this->unknown($name);
        }
        $storage = $this->storages->get($name);
        if (!$storage instanceof FileStorage) {
            throw new InvalidArgumentException(sprintf('The storage "%s" is no FileStorage.', $name));
        }

        return $storage;
    }

    private function unknown(string $name): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'There is no storage "%s" (byfareska_crud.files.storages has %s).',
            $name,
            implode(', ', $this->names()) ?: 'none',
        ));
    }
}
