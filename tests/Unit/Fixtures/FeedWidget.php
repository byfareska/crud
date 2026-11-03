<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsDashboardWidget;
use Byfareska\Crud\Dashboard\WidgetSetting;
use Byfareska\Crud\Dashboard\WidgetSettingType;

#[AsDashboardWidget(key: 'test.feed', title: 'feed', description: 'feed', icon: 'rss', settings: [
    new WidgetSetting('url', 'url', WidgetSettingType::Text, validator: FeedUrl::class),
])]
final class FeedWidget
{
}
