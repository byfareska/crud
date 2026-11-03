<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

/**
 * The way to the add form filled in as a copy of the record (?from=<id>): `actions: [new DuplicateAction()]`. Offered
 * to a resource that can add and read a record, for one its guards let read, where its driver can copy
 * (CopiesRecords: the doctrine and dto drivers). What else a copy takes - the lines of an order - is RecordCopying's.
 */
final readonly class DuplicateAction implements RecordAction
{
    public const string NAME = 'duplicate';

    /**
     * @param string $icon a Bootstrap Icons name
     * @param string|null $role only somebody with this role is offered the action
     * @param bool $inline in the list a button on the row instead of an item of the row menu
     */
    public function __construct(
        public string $icon = 'copy',
        public ?string $role = null,
        public bool $inline = false,
        public string $name = self::NAME,
    ) {
    }

    public function fields(): array
    {
        return [];
    }

    public function handler(): string
    {
        return DuplicateActionHandler::class;
    }
}
