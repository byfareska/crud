<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * {@see WidgetChoices} that depend on the dashboard they are offered on - the reports of that panel, its resources.
 * choices() is still asked where no dashboard is known.
 */
interface BoardWidgetChoices extends WidgetChoices
{
    /**
     * @return array<array-key, string|array<array-key, string>> as WidgetSetting::$choices
     */
    public function choicesFor(string $board): array;
}
