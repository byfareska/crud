<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * The choices of a WidgetSetting that come from a service (the zones, the pages that add a record...): a setting names
 * one with `choicesFrom`, by the index of its #[AsTaggedItem], and DashboardWidgets fills them in.
 */
interface WidgetChoices
{
    public const string TAG = 'byfareska_crud.widget_choices';

    /**
     * @return array<array-key, string|array<array-key, string>> as WidgetSetting::$choices
     */
    public function choices(): array;
}
