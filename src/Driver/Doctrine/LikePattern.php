<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

/**
 * A typed text in a LIKE pattern, its "%" and "_" taken as themselves - the same on every database: with an escape
 * character of its own, named in the query (SQLite has none by default, MySQL's backslash is no character of
 * PostgreSQL's standard strings):
 *
 *     sprintf('LOWER(%s) LIKE :%s ESCAPE \'%s\'', $path, $parameter, LikePattern::ESCAPE)
 *     ->setParameter($parameter, '%' . LikePattern::escape($text) . '%')
 */
final readonly class LikePattern
{
    public const string ESCAPE = '!';

    public static function escape(string $text): string
    {
        return strtr($text, [
            self::ESCAPE => self::ESCAPE . self::ESCAPE,
            '%' => self::ESCAPE . '%',
            '_' => self::ESCAPE . '_',
        ]);
    }
}
