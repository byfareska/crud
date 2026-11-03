<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The time zones of PHP, by continent, as the choices of a setting (`choicesFrom: TimezoneChoices::NAME`).
 */
#[AsTaggedItem(self::NAME)]
final readonly class TimezoneChoices implements WidgetChoices
{
    public const string NAME = 'panel.timezones';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, array<string, string>> continent => [zone => what to show], in the order of PHP's list
     */
    public function choices(): array
    {
        $groups = [];
        foreach (DateTimeZone::listIdentifiers() as $zone) {
            $parts = explode('/', $zone, 2);
            $continent = $this->translator->trans(
                'ui.timezone.region.' . (isset($parts[1]) ? strtolower($parts[0]) : 'other'),
            );
            $groups[$continent][$zone] = str_replace(['/', '_'], [' / ', ' '], $parts[1] ?? $parts[0]);
        }

        return $groups;
    }
}
