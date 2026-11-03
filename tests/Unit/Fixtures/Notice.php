<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Layout\Fieldset;
use Byfareska\Crud\Layout\PlacedLayoutNode;

/**
 * An info box of the application's own, among the fields of a fieldset or at the top.
 */
final readonly class Notice implements PlacedLayoutNode
{
    public function __construct(public string $text)
    {
    }

    public static function placedIn(): array
    {
        return ['top', Fieldset::class];
    }

    public static function holds(): array
    {
        return [];
    }
}
