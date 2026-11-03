<?php

declare(strict_types=1);

namespace Byfareska\Crud\Component\Widget;

use Byfareska\Crud\Attribute\AsDashboardWidget;
use Byfareska\Crud\Dashboard\DashboardWidgetProps;
use Byfareska\Crud\Dashboard\TimezoneChoices;
use Byfareska\Crud\Dashboard\WidgetSetting;
use Byfareska\Crud\Dashboard\WidgetSize;
use Byfareska\Crud\Panel\ScopedComponent;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Environment;
use Twig\Extension\CoreExtension;

/**
 * The clock of the dashboard: analog, Apple's face, or digital, in the zone Twig shows dates in (twig.date.timezone);
 * with a second city beside it (the "world clock"). It ticks in the browser (clock controller).
 */
#[AsDashboardWidget(
    key: 'panel.clock',
    title: 'panel.widget.clock.title',
    description: 'panel.widget.clock.description',
    icon: 'clock',
    accent: '#f97316',
    size: new WidgetSize(3, 2),
    minSize: new WidgetSize(2, 2),
    maxSize: new WidgetSize(6, 4),
    default: true,
    priority: 10,
    settings: [
        new WidgetSetting('style', 'panel.widget.clock.setting.style', choices: [
            'analog' => 'panel.widget.clock.style.analog',
            'digital' => 'panel.widget.clock.style.digital',
        ], default: 'analog'),
        new WidgetSetting(
            'zone',
            'panel.widget.clock.setting.zone',
            default: '',
            translateChoices: false,
            placeholder: 'panel.widget.clock.city.none',
            choicesFrom: TimezoneChoices::NAME,
        ),
    ],
    lazy: false,
    tone: 'day',
)]
#[AsTwigComponent('Panel:ClockWidget', template: 'components/Panel/ClockWidget.html.twig')]
final class ClockWidget implements ScopedComponent
{
    use DashboardWidgetProps;

    /**
     * Cities whose names are translated (panel.widget.clock.city.<key>); any other is written as its zone writes it.
     */
    public const array CITIES = [
        'UTC' => 'utc',
        'Europe/London' => 'london',
        'Europe/Warsaw' => 'warsaw',
        'Europe/Berlin' => 'berlin',
        'Europe/Kyiv' => 'kyiv',
        'America/New_York' => 'new_york',
        'America/Chicago' => 'chicago',
        'America/Los_Angeles' => 'los_angeles',
        'America/Sao_Paulo' => 'sao_paulo',
        'Asia/Dubai' => 'dubai',
        'Asia/Kolkata' => 'kolkata',
        'Asia/Shanghai' => 'shanghai',
        'Asia/Tokyo' => 'tokyo',
        'Australia/Sydney' => 'sydney',
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Day or night in the zone of the one signed in (6-18): a hint (Ui:Widget "tone") a theme may draw the card by.
     */
    public function getTone(): string
    {
        $hour = (int) new DateTimeImmutable('now', new DateTimeZone($this->zone()))->format('G');

        return $hour >= 6 && $hour < 18 ? 'day' : 'night';
    }

    public function isAnalog(): bool
    {
        return ($this->settings['style'] ?? 'analog') !== 'digital';
    }

    /**
     * Where the hours stand on a face of 100 x 100.
     *
     * @return list<array{hour: int, x: float, y: float}>
     */
    public function getNumerals(): array
    {
        $numerals = [];
        for ($hour = 1; $hour <= 12; ++$hour) {
            $angle = deg2rad($hour * 30);
            $numerals[] = [
                'hour' => $hour,
                'x' => round(50 + 33 * sin($angle), 2),
                'y' => round(50 - 33 * cos($angle), 2),
            ];
        }

        return $numerals;
    }

    /**
     * @return list<array{zone: string, label: string, flag: ?string}> the zone of the one signed in, then the second
     *     city; each labelled with its city, a finished text, and the flag of its country
     */
    public function getZones(): array
    {
        $own = $this->zone();
        $zones = [['zone' => $own, 'label' => $this->city($own), 'flag' => $this->flag($own)]];
        $second = $this->settings['zone'] ?? '';
        if (is_string($second) && $second !== '' && in_array($second, DateTimeZone::listIdentifiers(), true)) {
            $zones[] = ['zone' => $second, 'label' => $this->city($second), 'flag' => $this->flag($second)];
        }

        return $zones;
    }

    private function zone(): string
    {
        return $this->twig->getExtension(CoreExtension::class)->getTimezone()->getName();
    }

    /**
     * The city a zone is named after: translated when it is one of CITIES ("Warszawa"), else as the zone writes it
     * ("America/Argentina/Buenos_Aires" -> "Buenos Aires").
     */
    private function city(string $zone): string
    {
        if (isset(self::CITIES[$zone])) {
            return $this->translator->trans('panel.widget.clock.city.' . self::CITIES[$zone]);
        }

        $parts = explode('/', $zone);

        return str_replace('_', ' ', end($parts));
    }

    /**
     * The flag of the country of a zone as an emoji ("Europe/Warsaw" -> 🇵🇱), the country being PHP's own (tzdata's
     * zone.tab); null for a zone of no country ("UTC", "Etc/GMT+2").
     */
    private function flag(string $zone): ?string
    {
        $country = new DateTimeZone($zone)->getLocation()['country_code'] ?? null;

        // tzdata writes "??" for a zone of no country.
        return is_string($country) ? CountryFlag::of($country) : null;
    }
}
