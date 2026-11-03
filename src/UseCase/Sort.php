<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

use SortDirection;
use Stringable;

/**
 * What a list is ordered by, as a caller writes it in the "sort" field of a list DTO: the name of a field for
 * ascending, with a minus in front for descending ("name", "-createdAt"). The field is a list of them, the most
 * important first: what is equal by the first is ordered by the next. The same texts over REST (?sort=-name,email),
 * as an MCP tool argument and in the address of a list of the panel.
 *
 * The direction is PHP's own SortDirection (8.6; symfony/polyfill-php86 before).
 */
final readonly class Sort implements Stringable
{
    public function __construct(
        public string $field,
        public SortDirection $direction = SortDirection::Ascending,
    ) {
    }

    public static function parse(string $sort): self
    {
        return str_starts_with($sort, '-')
            ? new self(substr($sort, 1), SortDirection::Descending)
            : new self($sort);
    }

    /**
     * @param array<mixed> $sorts as written by a caller; a field named again is left out, and so is what is no text
     *
     * @return list<self>
     */
    public static function parseAll(array $sorts): array
    {
        $parsed = [];
        foreach ($sorts as $sort) {
            if (is_string($sort) && $sort !== '') {
                $one = self::parse($sort);
                $parsed[$one->field] ??= $one;
            }
        }

        return array_values($parsed);
    }

    public function isDescending(): bool
    {
        return $this->direction === SortDirection::Descending;
    }

    public function reversed(): self
    {
        return new self($this->field, $this->isDescending() ? SortDirection::Ascending : SortDirection::Descending);
    }

    public function __toString(): string
    {
        return ($this->isDescending() ? '-' : '') . $this->field;
    }
}
