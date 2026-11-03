<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver;

use Byfareska\Crud\Resource\CrudOperation;

/**
 * Where the records of a #[AsCrud] resource come from and go to, and which driver deals with them: an entity of
 * Doctrine ({@see Doctrine\DoctrineSource}), use cases behind DTOs ({@see Dto\DtoSource}) or a source of the
 * application's own, read by a {@see CrudDriver} of its own.
 */
interface CrudSource
{
    /**
     * The name of the driver ({@see CrudDriver::name()}).
     */
    public function driver(): string;

    /**
     * Whether the resource can do it. An operation the source cannot do has no page, no route and no button.
     */
    public function supports(CrudOperation $operation): bool;

    /**
     * The filter of the list that takes a typed text (what the command palette finds records by); null = none.
     */
    public function searchField(): ?string;
}
