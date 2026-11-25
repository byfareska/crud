<?php

declare(strict_types=1);

namespace Byfareska\Crud\Tests\Unit\Fixtures;

use Byfareska\Crud\Attribute\AsCrud;
use Byfareska\Crud\Resource\CrudRecords;
use Byfareska\Crud\Resource\RecordScreen;
use Byfareska\Crud\Resource\RecordView;

/**
 * Records by id - one not given is there, with nothing - and the ids deleted, in order.
 */
final class DeletedRecords implements CrudRecords
{
    /** @var list<string> */
    public array $deleted = [];

    /**
     * @param array<int|string, array<string, mixed>> $items by id
     */
    public function __construct(private readonly array $items = [])
    {
    }

    public function read(AsCrud $crud, string $id): array
    {
        return $this->items[$id] ?? [];
    }

    public function details(AsCrud $crud, string $id): array
    {
        return ['id' => $id, 'title' => null, 'rows' => [], 'item' => $this->read($crud, $id)];
    }

    public function view(AsCrud $crud, string $id, RecordScreen $screen): RecordView
    {
        return new RecordView($crud, $id, $this->read($crud, $id), $screen);
    }

    public function delete(AsCrud $crud, string $id): void
    {
        $this->deleted[] = $id;
    }
}
