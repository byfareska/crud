<?php

declare(strict_types=1);

namespace Byfareska\Crud\Attribute;

use Attribute;
use Byfareska\Crud\Navigation\TopbarWidget;

/**
 * Puts the Twig component it is on into the top bar of the panel, next to the user menu: the {@see TopbarWidget} of
 * that component, named by its #[AsTwigComponent]. The component is rendered without arguments.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTopbarWidget
{
    public const string TAG = 'byfareska_crud.topbar_widget';

    /**
     * @param int $priority lower comes first
     * @param string|null $component the name of the component; null = the one of its #[AsTwigComponent]
     */
    /**
     * @param string|null $scope the panel whose top bar has it; null = every panel
     * @param string|null $role only somebody with it sees it
     * @param array<string, mixed> $props what the component is given
     */
    public function __construct(
        public int $priority = 0,
        public ?string $component = null,
        public ?string $scope = null,
        public ?string $role = null,
        public array $props = [],
    ) {
    }

    public function widget(string $component): TopbarWidget
    {
        return new TopbarWidget($component, $this->priority, $this->scope, $this->role, $this->props);
    }
}
