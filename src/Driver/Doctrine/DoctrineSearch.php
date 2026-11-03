<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * How a typed text finds the entities of a {@see DoctrineSource}: in its list (the filter "query", also what the
 * command palette finds records by) and in the relation filters of other lists that offer them as one types. The
 * bundle's is {@see WordsSearch}: every word in one of the fields, in part, any case.
 *
 * Another one - full-text (MATCH ... AGAINST, to_tsvector), an exact SKU first, a search engine giving ids - is a
 * service implementing this, autoconfigured: alias the interface to it for every resource, or name it for one,
 * `new DoctrineSource(Product::class, search: ['name', 'sku'], searchWith: SkuSearch::class)`.
 */
#[AutoconfigureTag(self::TAG)]
interface DoctrineSearch
{
    public const string TAG = 'byfareska_crud.doctrine_search';

    /**
     * Narrows the query to the entities the text finds - conditions (andWhere()), joins of its own; not the order of
     * the list (that is its headings'), not the page.
     *
     * @param list<string> $fields what the source searches, also through a relation ("category.name": $query->path())
     * @param string $text as typed, trimmed and not empty
     */
    public function apply(DoctrineQuery $query, array $fields, string $text): void;
}
