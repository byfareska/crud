<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Filter\Comparison;
use Byfareska\Crud\Filter\ComparisonKind;
use Byfareska\Crud\Filter\ComparisonType;
use InvalidArgumentException;

/**
 * The bundle's filter of a field or a relation of the entity, compared the ways it offers ({@see ComparisonType}).
 * What {@see DoctrineFilterGuesser} makes of a field named in "filters" - or given there to say what it offers:
 *
 *     filters: [
 *         'createdAt' => new FieldFilter('createdAt', [Comparison::AtLeast]),
 *         'categoryName' => new FieldFilter('category.name', Comparison::TEXT),
 *     ]
 *
 * Made by {@see FieldFilterHandler}.
 */
final readonly class FieldFilter implements DoctrineFilter
{
    /**
     * @param string $field of the entity, a relation of it, or a field of a related entity ("category.name")
     * @param list<ComparisonKind> $comparisons what can be chosen, at least one; the first is what is chosen at first -
     *     the bundle's Comparisons, or ways of the application's own (DoctrineComparison)
     * @param string|null $type the form type of the value; null = a text
     * @param array<string, mixed> $options of the field of the value
     */
    public function __construct(
        public string $field,
        public array $comparisons,
        public ?string $type = null,
        public array $options = [],
    ) {
        $known = static fn (mixed $c): bool => $c instanceof Comparison || $c instanceof DoctrineComparison;
        if ($comparisons === [] || !array_all($comparisons, $known)) {
            throw new InvalidArgumentException(sprintf(
                'The filter of "%s" compares at least one way, each a case of %s or a %s.',
                $field,
                Comparison::class,
                DoctrineComparison::class,
            ));
        }
    }

    public function handler(): string
    {
        return FieldFilterHandler::class;
    }
}
