<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Doctrine\ORM\QueryBuilder;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use SortDirection;

/**
 * The direction of an ORDER BY as the QueryBuilder of the installed Doctrine ORM takes it: the SortDirection enum
 * (PHP 8.6, symfony/polyfill-php86) since ORM 3.7, which deprecates "ASC" / "DESC"; the text before it.
 */
final class DoctrineOrder
{
    private static ?bool $takesEnum = null;

    public static function of(bool $descending): SortDirection|string
    {
        self::$takesEnum ??= self::takesEnum();
        if (self::$takesEnum) {
            return $descending ? SortDirection::Descending : SortDirection::Ascending;
        }

        return $descending ? 'DESC' : 'ASC';
    }

    private static function takesEnum(): bool
    {
        if (!enum_exists(SortDirection::class)) {
            return false;
        }
        $type = new ReflectionMethod(QueryBuilder::class, 'addOrderBy')->getParameters()[1]->getType();
        $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];

        return array_any(
            $types,
            static fn ($one): bool => $one instanceof ReflectionNamedType && $one->getName() === SortDirection::class,
        );
    }
}
