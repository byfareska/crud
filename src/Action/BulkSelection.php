<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\UseCase\Page;
use Closure;

/**
 * The records chosen in the list of a resource: by id - also those chosen on another page of it - or every record the
 * filters of the list match ("select all N matching"), however many pages they take.
 *
 * $ids is what a bulk action goes through in both cases: for every match they are read from the list when asked for
 * first (page by page, with its filters and scopes), so a button that only shows how many uses $count instead. A
 * handler that will not do every match - too many for it - offers no button when $allMatching.
 */
final class BulkSelection
{
    // phpcs:disable PSR2.Classes.PropertyDeclaration -- property hooks (PHP 8.4), which the sniff does not know
    /**
     * The ids of the records, as in a URL.
     *
     * @var list<string>
     */
    public array $ids {
        get => $this->matching instanceof Closure ? ($this->matched ??= ($this->matching)()) : $this->chosen;
    }

    /** Whether it is every record the filters of the list match. */
    public bool $allMatching {
        get => $this->matching instanceof Closure;
    }
    // phpcs:enable PSR2.Classes.PropertyDeclaration

    /** How many records it is, without reading them. */
    public readonly int $count;

    /** @var list<string>|null */
    private ?array $matched = null;

    /**
     * @param list<string> $chosen the records chosen, as in a URL; ignored when $matching is given
     * @param Page|null $list the page of the list shown, when there is one
     * @param (Closure(): list<string>)|null $matching every record the filters match, when that was chosen
     * @param int|null $count how many there are; null = count($ids)
     * @param array<string, mixed> $filters the filters of the list, as in its address: what a job sent to a queue
     *     (or an export) reads the records again with - CrudListQuery::ids() / items()
     * @param string|null $sort the order of the list, as in its address
     * @param (Closure(): iterable<string>)|null $streaming every record the filters match, given as read
     * @param string|null $input what was typed or chosen in the dialog that asked before the action (ConfirmInput):
     *     the status the records are set to, the reason
     * @param array<string, string> $parameters the parameters of the address of the page (PageParameters: the tenant
     *     of /{tenant}/admin, the category of a list of its products) - what a job sent to a queue carries with the
     *     filters, and its handler runs within (PageParameters::within())
     * @param string|null $actor the id of the one who chose them (Actor::id()): whom a job does it for
     */
    public function __construct(
        public readonly AsCrud $crud,
        private readonly array $chosen = [],
        public readonly ?Page $list = null,
        private readonly ?Closure $matching = null,
        ?int $count = null,
        public readonly array $filters = [],
        public readonly ?string $sort = null,
        private readonly ?Closure $streaming = null,
        public readonly ?string $input = null,
        public readonly array $parameters = [],
        public readonly ?string $actor = null,
    ) {
        $this->count = $count ?? count($this->chosen);
    }

    /**
     * The ids one by one, as they are read - for every match without gathering them all first (a hundred thousand
     * records archived in a loop flushing every few hundred). The ids chosen as they are.
     *
     * @return iterable<string>
     */
    public function each(): iterable
    {
        if ($this->matching instanceof Closure && $this->matched === null && $this->streaming instanceof Closure) {
            return ($this->streaming)();
        }

        return $this->ids;
    }
}
