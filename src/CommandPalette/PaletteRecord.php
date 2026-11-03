<?php

declare(strict_types=1);

namespace Byfareska\Crud\CommandPalette;

/**
 * A stored record found for what was typed into the command palette. Unlike a {@see PaletteEntry} its name and
 * description are data, shown as they are.
 */
final readonly class PaletteRecord
{
    /**
     * @param string $key unique across the application, e.g. "crud.user.record.<id>"
     * @param string $group translation key of the heading it is listed under
     * @param string $icon identifier without vendor prefix (e.g. "person")
     * @param array<string, scalar|list<scalar>> $routeParameters
     * @param string|null $scope only the palette of this panel (route scope) finds it; null = every panel
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $name,
        public string $description,
        public string $icon,
        public string $routeName,
        public array $routeParameters = [],
        public ?string $scope = null,
    ) {
    }
}
