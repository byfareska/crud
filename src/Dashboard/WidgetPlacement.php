<?php

declare(strict_types=1);

namespace Byfareska\Crud\Dashboard;

/**
 * One widget on a dashboard: where it is (cells of a grid of 12 columns), how large and with which settings. The same
 * widget may be placed more than once (two clocks), so a placement has an id of its own.
 */
final readonly class WidgetPlacement
{
    /**
     * @param string $id of the placement, unique in its layout
     * @param string $widget the key of the widget (DashboardWidget::$key)
     * @param array<string, string|list<string>> $settings
     */
    public function __construct(
        public string $id,
        public string $widget,
        public int $x,
        public int $y,
        public int $w,
        public int $h,
        public array $settings = [],
    ) {
    }

    public static function newId(): string
    {
        return bin2hex(random_bytes(6));
    }

    public function movedTo(int $x, int $y, int $w, int $h): self
    {
        return new self($this->id, $this->widget, $x, $y, $w, $h, $this->settings);
    }

    /**
     * @param array<string, string|list<string>> $settings
     */
    public function withSettings(array $settings): self
    {
        return new self($this->id, $this->widget, $this->x, $this->y, $this->w, $this->h, $settings);
    }

    public function overlaps(self $other): bool
    {
        return $this->x < $other->x + $other->w && $other->x < $this->x + $this->w
            && $this->y < $other->y + $other->h && $other->y < $this->y + $this->h;
    }

    /**
     * @return array{
     *     id: string,
     *     widget: string,
     *     x: int,
     *     y: int,
     *     w: int,
     *     h: int,
     *     settings: array<string, string|list<string>>,
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'widget' => $this->widget,
            'x' => $this->x,
            'y' => $this->y,
            'w' => $this->w,
            'h' => $this->h,
            'settings' => $this->settings,
        ];
    }

    /**
     * What was stored; null for something that is no placement.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (!is_string($data['id'] ?? null) || !is_string($data['widget'] ?? null)) {
            return null;
        }

        $settings = [];
        foreach (is_array($data['settings'] ?? null) ? $data['settings'] : [] as $name => $value) {
            if (is_string($value)) {
                $settings[(string) $name] = $value;
            } elseif (is_array($value)) {
                $settings[(string) $name] = array_values(array_filter($value, is_string(...)));
            }
        }

        return new self(
            $data['id'],
            $data['widget'],
            self::int($data['x'] ?? 0),
            self::int($data['y'] ?? 0),
            self::int($data['w'] ?? 1),
            self::int($data['h'] ?? 1),
            $settings,
        );
    }

    /**
     * What was stored, without what is no placement.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<self>
     */
    public static function listFromArray(array $data): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $one): ?self => is_array($one) ? self::fromArray($one) : null,
            $data,
        )));
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
