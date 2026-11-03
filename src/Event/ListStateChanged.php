<?php

declare(strict_types=1);

namespace Byfareska\Crud\Event;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * The one looking changed the filters, the order or the size of a page of a list (dispatched once a request, when the
 * list is drawn again): what a listener keeps as their choice - for a ListDefaults of the application to start the
 * list with it next time.
 */
final readonly class ListStateChanged
{
    /**
     * @param array<string, mixed> $filters those in use, as in the address
     * @param string|null $sort as in the address; null = the order of the list itself
     * @param int|null $perPage null = the size it shows at first
     */
    public function __construct(
        public AsCrud $crud,
        public array $filters,
        public ?string $sort,
        public ?int $perPage,
    ) {
    }
}
