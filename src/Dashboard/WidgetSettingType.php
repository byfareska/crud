<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * What a WidgetSetting is changed with.
 */
enum WidgetSettingType: string
{
    /** One of the choices. */
    case Choice = 'choice';
    /** A text. */
    case Text = 'text';
    /** Any of the choices. */
    case Multiple = 'multiple';
    /** A number, from WidgetSetting::$min to $max, by $step: a threshold of an alert, how many rows. */
    case Number = 'number';
    /** On or off: "1" or "". */
    case Toggle = 'toggle';
    /** A day, Y-m-d. */
    case Date = 'date';
    /** A colour, #rrggbb. */
    case Color = 'color';
    /** A text the widget keeps itself (the text of a note): not in the dialog of the settings. */
    case Hidden = 'hidden';
}
