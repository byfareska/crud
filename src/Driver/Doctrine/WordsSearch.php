<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * The bundle's {@see DoctrineSearch}: the text is split into words, and every word has to be in one of the fields - in
 * part, any case (LOWER(...) LIKE %word%). A field of numbers is not text: a word that is a number has to be it
 * ("1024" finds the order 1024, not 10245), any other word is not looked for there - PostgreSQL has no LOWER() of a
 * number, nor of a uuid or JSON, which are left out on it too. Works on every database, uses no index for a part of a
 * word: for many records a full-text search of the database is a DoctrineSearch of the application's own.
 */
final readonly class WordsSearch implements DoctrineSearch
{
    private const array TEXT_TYPES = ['string', 'text', 'ascii_string'];

    private const array INTEGER_TYPES = ['integer', 'smallint', 'bigint'];

    private const array DECIMAL_TYPES = ['decimal', 'float', 'smallfloat'];

    public function apply(DoctrineQuery $query, array $fields, string $text): void
    {
        if ($fields === []) {
            return;
        }
        $postgres = $query->query->getEntityManager()->getConnection()->getDatabasePlatform()
            instanceof PostgreSQLPlatform;

        foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $index => $word) {
            $parameter = $query->parameter('search' . $index);
            $any = [];
            $number = null;
            $liked = false;
            foreach ($fields as $field) {
                $type = $query->typeOf($field);
                if (in_array($type, self::INTEGER_TYPES, true) || in_array($type, self::DECIMAL_TYPES, true)) {
                    $value = $this->number($word, in_array($type, self::INTEGER_TYPES, true));
                    if ($value !== null) {
                        $number ??= $query->parameter('number' . $index);
                        $any[] = sprintf('%s = :%s', $query->path($field), $number);
                        $query->query->setParameter($number, $value);
                    }

                    continue;
                }
                // A date, a uuid, JSON: text on MySQL and SQLite, no LOWER() of it on PostgreSQL.
                if ($postgres && $type !== null && !in_array($type, self::TEXT_TYPES, true)) {
                    continue;
                }
                $any[] = sprintf(
                    "LOWER(%s) LIKE :%s ESCAPE '%s'",
                    $query->path($field),
                    $parameter,
                    LikePattern::ESCAPE,
                );
                $liked = true;
            }
            if ($any === []) {
                // In no field it could be: nothing has it.
                $query->query->andWhere('1 = 0');

                continue;
            }
            $query->query->andWhere($query->query->expr()->orX(...$any));
            if ($liked) {
                $query->query->setParameter($parameter, '%' . LikePattern::escape(mb_strtolower($word)) . '%');
            }
        }
    }

    /**
     * The word as a number of the field, if it is one.
     */
    private function number(string $word, bool $integer): int|string|null
    {
        if ($integer) {
            return preg_match('/^-?\d{1,18}$/', $word) === 1 ? (int) $word : null;
        }
        $decimal = str_replace(',', '.', $word);

        return is_numeric($decimal) ? $decimal : null;
    }
}
