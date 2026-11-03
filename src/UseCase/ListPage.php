<?php

declare(strict_types=1);

namespace Byfareska\Crud\UseCase;

/**
 * A ready {@see Page}: what a list use case (or a driver) returns when it has nothing of its own to say.
 */
final readonly class ListPage implements Page, CountsApproximately
{
    public int $pageCount;

    public int $page;

    /**
     * @param list<array<string, mixed>> $items
     * @param bool $more there are more records than $total, which is as far as they were counted (a list of a table
     *     too large to count): "more than N", paged up to it
     */
    public function __construct(
        public array $items,
        public int $total,
        int $page,
        public int $perPage,
        public bool $more = false,
    ) {
        $this->pageCount = max(1, (int) ceil($total / max(1, $perPage)));
        $this->page = max(1, min($page, $this->pageCount));
    }
}
