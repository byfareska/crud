<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Filter\Compared;
use Byfareska\Crud\Filter\ComparisonKind;

/**
 * A way of the application's own a {@see FieldFilter} compares by, offered next to the bundle's Comparisons - what
 * it adds to the query of the list is its own:
 *
 *     final readonly class LastDays implements DoctrineComparison
 *     {
 *         public function key(): string { return 'last_days'; }
 *         public function label(): string { return 'app.filter.last_days'; }
 *         public function needsValue(): bool { return true; }
 *         public function isMany(): bool { return false; }
 *
 *         public function apply(DoctrineListQuery $query, string $path, string $parameter, Compared $value): void
 *         {
 *             $query->query->andWhere(sprintf('%s >= :%s', $path, $parameter))
 *                 ->setParameter($parameter, new DateTimeImmutable(sprintf('-%d days', (int) $value->value)));
 *         }
 *     }
 *
 *     'updatedAt' => new FieldFilter('updatedAt', [...Comparison::DATE, new LastDays()], IntegerType::class)
 *
 * The value is what the field of the filter makes of what was typed (its form type); with isMany() a list of it.
 */
interface DoctrineComparison extends ComparisonKind
{
    /**
     * @param string $path the field in the query ("e.updatedAt", "category_1.name")
     * @param string $parameter a name of a parameter of its own - more of them with a suffix ("{$parameter}_to")
     */
    public function apply(DoctrineListQuery $query, string $path, string $parameter, Compared $value): void;
}
