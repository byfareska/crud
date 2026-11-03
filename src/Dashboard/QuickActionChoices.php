<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * The quick actions (pages that add a record) as the choices of a setting (`choicesFrom: QuickActionChoices::NAME`):
 * key => the name of its entry of the palette.
 */
#[AsTaggedItem(self::NAME)]
final readonly class QuickActionChoices implements BoardWidgetChoices
{
    public const string NAME = 'panel.quick_actions';

    public function __construct(
        private QuickActions $quickActions,
    ) {
    }

    public function choices(): array
    {
        return $this->of(null);
    }

    public function choicesFor(string $board): array
    {
        return $this->of($board);
    }

    /**
     * Only what the one setting up the widget may use, on that dashboard: the others would be tiles nobody can click.
     *
     * @return array<string, string>
     */
    private function of(?string $board): array
    {
        $choices = [];
        foreach ($this->quickActions->available(board: $board) as $action) {
            $choices[$action['entry']->key] = $action['entry']->name;
        }

        return $choices;
    }
}
