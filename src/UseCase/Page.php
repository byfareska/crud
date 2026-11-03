<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

/**
 * One page of a longer list. What a list use case returns when its result is shown by a generic list.
 */
interface Page
{
    /**
     * @var list<array<string, mixed>> the records on this page, by field
     */
    public array $items { get; }

    /**
     * Records matching the request, across all pages.
     */
    public int $total { get; }

    /**
     * The current page, clamped to the available range.
     */
    public int $page { get; }

    public int $pageCount { get; }

    public int $perPage { get; }
}
