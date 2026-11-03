<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * Something the user may change about one widget of the dashboard (a period, a second clock...). The panel draws the
 * form of the settings from these; the widget gets the values as its `settings`.
 */
final readonly class WidgetSetting
{
    /**
     * @param string $name the key of the value in the settings
     * @param string $label translation key
     * @param array<array-key, string|array<array-key, string>> $choices value => translation key (Choice, Multiple); or
     *     groups: a label => [value => label, ...]
     * @param string|list<string> $default
     * @param bool $translateChoices false when the labels of the choices are finished texts (zones)
     * @param string|null $placeholder translation key of an empty choice offered first ("none"); its value is ""
     * @param bool $searchable a field to type in, its choices filtered (many choices: the zones)
     * @param string|null $choicesFrom the index of a WidgetChoices service whose choices replace $choices (for
     *     #[AsDashboardWidget], whose arguments are constants)
     * @param bool|null $personal what only its user is to see - a note: left out (the default) when the layout is
     *     shared with others; null = a Hidden setting is, the others are not
     * @param int $maxLength the longest text kept (Text, Hidden)
     * @param class-string<WidgetSettingValidator>|null $validator a service that checks the value further when the user
     *     sets it
     * @param int|float|null $min the smallest number (Number); null = none
     * @param int|float|null $max the largest number (Number); null = none
     * @param int|float|null $step what a number goes by (Number): 1 for whole numbers; null = any
     */
    public function __construct(
        public string $name,
        public string $label,
        public WidgetSettingType $type = WidgetSettingType::Choice,
        public array $choices = [],
        public string|array $default = '',
        public bool $translateChoices = true,
        public ?string $placeholder = null,
        public bool $searchable = false,
        public ?string $choicesFrom = null,
        public ?bool $personal = null,
        public int $maxLength = 10000,
        public ?string $validator = null,
        public int|float|null $min = null,
        public int|float|null $max = null,
        public int|float|null $step = null,
    ) {
    }

    /**
     * Whether only its user is to see it: left out when the layout is shared.
     */
    public function isPersonal(): bool
    {
        return $this->personal ?? $this->type === WidgetSettingType::Hidden;
    }

    /**
     * This setting with the choices of its WidgetChoices service.
     *
     * @param array<array-key, string|array<array-key, string>> $choices
     */
    public function withChoices(array $choices): self
    {
        return new self(
            $this->name,
            $this->label,
            $this->type,
            $choices,
            $this->default,
            $this->translateChoices,
            $this->placeholder,
            $this->searchable,
            // Still named: the choices of all() are those of every dashboard, those of available($board) of one.
            $this->choicesFrom,
            personal: $this->personal,
            maxLength: $this->maxLength,
            validator: $this->validator,
            min: $this->min,
            max: $this->max,
            step: $this->step,
        );
    }

    /**
     * The values one may choose, the groups left aside.
     *
     * @return list<string>
     */
    public function values(): array
    {
        $values = [];
        foreach ($this->choices as $value => $label) {
            if (is_array($label)) {
                array_push($values, ...array_map(strval(...), array_keys($label)));
            } else {
                $values[] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * Whether it is changed in the dialog of the settings.
     */
    public function isEditable(): bool
    {
        return $this->type !== WidgetSettingType::Hidden;
    }

    /**
     * What is kept of a value given for this setting: one of the choices, a text of a sane length, or the default.
     *
     * @return string|list<string>
     */
    public function accept(mixed $value): string|array
    {
        return match ($this->type) {
            WidgetSettingType::Choice => is_scalar($value) && $this->isChoice((string) $value)
                ? (string) $value
                : $this->default,
            WidgetSettingType::Multiple => is_array($value)
                ? array_values(array_filter(
                    array_map(strval(...), array_filter($value, is_scalar(...))),
                    fn (string $one): bool => in_array($one, $this->values(), true),
                ))
                : $this->default,
            WidgetSettingType::Text, WidgetSettingType::Hidden => is_scalar($value)
                ? mb_substr((string) $value, 0, $this->maxLength)
                : $this->default,
            WidgetSettingType::Number => $this->number($value) ?? $this->default,
            WidgetSettingType::Toggle => in_array($value, [true, 1, '1', 'true', 'on'], true) ? '1' : '',
            WidgetSettingType::Date => is_string($value) && $this->isDay($value) ? $value : $this->default,
            WidgetSettingType::Color => is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) === 1
                ? strtolower($value)
                : $this->default,
        };
    }

    /** A number within $min and $max, by $step, as a text; null for anything else. */
    private function number(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        if (($this->min !== null && $number < $this->min) || ($this->max !== null && $number > $this->max)) {
            return null;
        }
        if ($this->step !== null && $this->step > 0) {
            $steps = ($number - ($this->min ?? 0)) / $this->step;
            if (abs($steps - round($steps)) > 1e-9) {
                return null;
            }
        }

        return fmod($number, 1.0) === 0.0 && abs($number) < 1e15 ? (string) (int) $number : (string) $number;
    }

    private function isDay(string $value): bool
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $day instanceof \DateTimeImmutable && $day->format('Y-m-d') === $value;
    }

    /** One of the choices, or "" when there is an empty choice. */
    private function isChoice(string $value): bool
    {
        return in_array($value, $this->values(), true) || ($this->placeholder !== null && $value === '');
    }
}
