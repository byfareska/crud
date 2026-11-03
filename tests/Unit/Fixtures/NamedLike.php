<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Driver\Doctrine\DoctrineComparison;
use Byfareska\Crud\Driver\Doctrine\DoctrineListQuery;
use Byfareska\Crud\Filter\Compared;

/**
 * A way of the application's own: the text is a SQL LIKE pattern, as typed.
 */
final readonly class NamedLike implements DoctrineComparison
{
    public function key(): string
    {
        return 'like';
    }

    public function label(): string
    {
        return 'app.filter.like';
    }

    public function needsValue(): bool
    {
        return true;
    }

    public function isMany(): bool
    {
        return false;
    }

    public function apply(DoctrineListQuery $query, string $path, string $parameter, Compared $value): void
    {
        $query->query->andWhere(sprintf('%s LIKE :%s', $path, $parameter))->setParameter($parameter, $value->value);
    }
}
