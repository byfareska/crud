<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * Something the command palette lists, with its texts in the language of the one looking: an entry
 * ({@see PaletteEntry}, translated) or a stored record ({@see PaletteRecord}, as it is).
 */
final readonly class PaletteItem
{
    /**
     * @param string $group translation key of its heading
     * @param array<string, scalar|list<scalar>> $routeParameters
     * @param list<string> $keywords more words it is found by, not shown
     * @param bool $remembered kept among the recent ones when chosen (a record is not)
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $name,
        public string $description,
        public string $icon,
        public ?string $routeName = null,
        public array $routeParameters = [],
        public ?string $clientAction = null,
        public bool $command = false,
        public array $keywords = [],
        public bool $remembered = true,
    ) {
    }

    /** The same, under another heading ("recent", "suggested"). */
    public function inGroup(string $group): self
    {
        return new self(
            $this->key,
            $group,
            $this->name,
            $this->description,
            $this->icon,
            $this->routeName,
            $this->routeParameters,
            $this->clientAction,
            $this->command,
            $this->keywords,
            $this->remembered,
        );
    }
}
