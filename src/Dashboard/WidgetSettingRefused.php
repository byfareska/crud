<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use RuntimeException;

/**
 * Thrown by a {@see WidgetSettingValidator} for a value it will not keep and the user is to correct ("this is no feed
 * of RSS"): nothing is saved, and the dialog of the settings shows the message at the setting.
 */
final class WidgetSettingRefused extends RuntimeException
{
    /**
     * @param string $translationKey what to tell the user
     * @param array<string, string|int> $parameters of the translation
     */
    public function __construct(
        public readonly string $translationKey,
        public readonly array $parameters = [],
        public ?string $setting = null,
    ) {
        parent::__construct($translationKey);
    }
}
