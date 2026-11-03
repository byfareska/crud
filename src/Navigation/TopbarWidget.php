<?php

declare(strict_types=1);

namespace Byfareska\Crud\Navigation;

/**
 * Something shown in the top bar of the panel, next to the user menu: a Twig component of its own.
 */
final readonly class TopbarWidget
{
    /**
     * @param string $component the name of the Twig component
     * @param int $priority lower comes first
     * @param string|null $scope the panel whose top bar has it; null = every panel
     * @param string|null $role only somebody with it sees it
     * @param array<string, mixed> $props what the component is given
     */
    public function __construct(
        public string $component,
        public int $priority = 0,
        public ?string $scope = null,
        public ?string $role = null,
        public array $props = [],
    ) {
    }
}
