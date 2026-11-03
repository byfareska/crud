<?php

declare(strict_types=1);

namespace Byfareska\Crud\Navigation;

use Byfareska\Crud\Attribute\AsTopbarWidget;
use LogicException;
use ReflectionClass;

/**
 * What #[AsTopbarWidget] puts into the top bar (collected by CollectWidgetsPass).
 */
final readonly class AttributeTopbarWidgets implements TopbarWidgetSource
{
    /**
     * @param array<class-string, string> $components the name of the component by its class
     */
    public function __construct(
        private array $components = [],
    ) {
    }

    public function widgets(): iterable
    {
        foreach ($this->components as $class => $component) {
            $attributes = new ReflectionClass($class)->getAttributes(AsTopbarWidget::class);
            if ($attributes === []) {
                throw new LogicException(sprintf('"%s" has no #[AsTopbarWidget].', $class));
            }

            yield $attributes[0]->newInstance()->widget($component);
        }
    }
}
