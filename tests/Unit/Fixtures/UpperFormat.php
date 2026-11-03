<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Value\ValueFormat;
use Byfareska\Crud\Value\ValueFormatter;

/** A format of the application's own: see UpperFormatter. */
final readonly class UpperFormat implements ValueFormat
{
    /**
     * @param class-string<ValueFormatter> $formatter
     */
    public function __construct(
        private string $formatter = UpperFormatter::class,
    ) {
    }

    public function formatter(): string
    {
        return $this->formatter;
    }
}
