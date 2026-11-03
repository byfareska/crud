<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Checks a value of a widget setting beyond its type, when the user sets it - a URL of a feed, a number within a range,
 * a date: the setting names it, `new WidgetSetting('feed', ..., WidgetSettingType::Text, validator: FeedUrl::class)`.
 * A service, autoconfigured.
 */
#[AutoconfigureTag(self::TAG)]
interface WidgetSettingValidator
{
    public const string TAG = 'byfareska_crud.widget_setting_validator';

    /**
     * What is kept of the value: it as it is, made right (trimmed, a number in range), or the default of the setting
     * when it will not do - or, for a value the user is to correct, a WidgetSettingRefused: nothing is saved and the
     * dialog shows its message at the setting.
     *
     * @param string|list<string> $value what the setting accepted by its type already
     *
     * @return string|list<string>
     *
     * @throws WidgetSettingRefused
     */
    public function validate(WidgetSetting $setting, string|array $value, string $board): string|array;
}
