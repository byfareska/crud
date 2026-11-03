<?php

declare(strict_types=1);

namespace Byfareska\Crud\Action;

use Byfareska\Crud\Attribute\AsCrud;

/**
 * Something that can be done with the records chosen in the list - declared in {@see AsCrud::$bulkActions}; with one
 * there, the rows of the list can be chosen. It only says what it is: the {@see BulkActionHandler} it names (a
 * service) makes its button for the records chosen and does it. The bundle's kind: {@see BulkDeleteAction}.
 *
 * Its text is the translation crud.<resource>.bulk.<name>, unless its button says otherwise.
 */
interface BulkAction
{
    /** Lower-case; tells the bulk actions of a resource apart. */
    public string $name { get; }

    /** Only somebody with this role is offered it; null = everybody the resource is for. */
    public ?string $role { get; }

    /**
     * The service that makes its button and does it.
     *
     * @return class-string<BulkActionHandler>
     */
    public function handler(): string;
}
