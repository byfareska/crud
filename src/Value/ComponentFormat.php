<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

/**
 * The values of a column printed by a Twig component of the application: a progress bar of the stock, an avatar with
 * a name. The component gets "cell" ({@see ValueView}: the value, the whole record, where it is printed) and the props
 * given here; it declares them (as public properties, or {% props %} of an anonymous component) - objects make no
 * HTML attributes. See {@see ValueFormat}.
 */
final readonly class ComponentFormat implements ValueFormat
{
    /**
     * @param string $component name of the Twig component, e.g. "App:StockLevel"
     * @param array<string, mixed> $props what else the component gets
     */
    public function __construct(
        public string $component,
        public array $props = [],
    ) {
    }

    public function formatter(): string
    {
        return ComponentFormatter::class;
    }
}
