<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Dashboard\WidgetSetting;
use Byfareska\Crud\Dashboard\WidgetSettingRefused;
use Byfareska\Crud\Dashboard\WidgetSettingValidator;

final readonly class FeedUrl implements WidgetSettingValidator
{
    public function validate(WidgetSetting $setting, string|array $value, string $board): string|array
    {
        if (is_string($value) && !str_starts_with($value, 'https://')) {
            throw new WidgetSettingRefused('app.feed.not_https', ['url' => $value]);
        }

        return $value;
    }
}
