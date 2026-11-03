<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Dto;

use Byfareska\Crud\Driver\CrudSource;
use Byfareska\Crud\Resource\CrudOperation;
use Byfareska\Crud\UseCase\MapsToRequest;

/**
 * Records behind use cases: each operation is a DTO naming its use case with #[UseCase], run by the UseCaseExecutor
 * (driver "dto"). The same DTOs can be the input of an API, a console command or an MCP tool, so the panel validates
 * and converts exactly as they do. What the DTOs have to look like:
 *
 * - list: a form type (#[AsFormType]) whose use case returns a Page (ListPage will do). The fields "page", "perPage",
 *   "fields" and "sort" are driven by the list when the DTO has them; every other field is a filter. "sort" is a
 *   multiple choice of field names, each also with a minus in front for descending (see Sort).
 * - read, delete: a public "id" property. "read" returns an object whose public properties are the record.
 * - create, update: a form type; update also has a public "id" property. The edit form starts with the values of
 *   the same-named properties of what "read" returns (and FillsFromRecord for anything structured).
 *
 * Usually written as the shorthand list:, read:, create:, update:, delete: of #[AsCrud].
 */
final readonly class DtoSource implements CrudSource
{
    public const string DRIVER = 'dto';

    /**
     * @param class-string<MapsToRequest>|null $list
     * @param class-string<MapsToRequest>|null $read
     * @param class-string<MapsToRequest>|null $create
     * @param class-string<MapsToRequest>|null $update
     * @param class-string<MapsToRequest>|null $delete
     * @param string|null $search the field of the list DTO that takes a typed text, e.g. "query"
     */
    public function __construct(
        public ?string $list = null,
        public ?string $read = null,
        public ?string $create = null,
        public ?string $update = null,
        public ?string $delete = null,
        public ?string $search = null,
    ) {
    }

    public function driver(): string
    {
        return self::DRIVER;
    }

    public function supports(CrudOperation $operation): bool
    {
        return $this->dto($operation) !== null;
    }

    public function searchField(): ?string
    {
        return $this->search;
    }

    /**
     * @return class-string<MapsToRequest>|null
     */
    public function dto(CrudOperation $operation): ?string
    {
        return match ($operation) {
            CrudOperation::List => $this->list,
            CrudOperation::Read => $this->read,
            CrudOperation::Create => $this->create,
            CrudOperation::Update => $this->update,
            CrudOperation::Delete => $this->delete,
        };
    }
}
