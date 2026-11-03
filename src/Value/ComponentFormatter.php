<?php

declare(strict_types=1);

namespace Byfareska\Crud\Value;

use InvalidArgumentException;
use Symfony\UX\TwigComponent\ComponentRendererInterface;

/**
 * Prints a value of a {@see ComponentFormat} with its component.
 */
final readonly class ComponentFormatter implements ValueFormatter
{
    public function __construct(
        private ComponentRendererInterface $components,
    ) {
    }

    public function format(ValueFormat $format, ValueView $value): string
    {
        if (!$format instanceof ComponentFormat) {
            throw new InvalidArgumentException(sprintf(
                '%s prints the values of a %s.',
                self::class,
                ComponentFormat::class,
            ));
        }

        return $this->components->createAndRender($format->component, ['cell' => $value] + $format->props);
    }
}
