<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Doctrine;

use Byfareska\Crud\Value\CrudValues;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * The id of an entity as an address carries it, and back: what the list, the pages, the relations and the palette go
 * by. The bundle's is {@see DefaultDoctrineIdCodec} - an id of one field as it is, one of several fields (an order
 * line: order and number) as its parts, each encoded, joined by ",". Alias it for ids of your own kind - a hash of the
 * id, a slug - everywhere at once.
 */
interface DoctrineIdCodec
{
    /**
     * The id of an entity; null when it has none (not persisted yet).
     *
     * @param ClassMetadata<object> $metadata
     */
    public function encode(ClassMetadata $metadata, object $entity, CrudValues $values): ?string;

    /**
     * What finds the entity of an id: its identifier fields => their values (of a relation: the id of the related
     * entity); null when the id names nothing of that entity - another kind, a part missing.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return non-empty-array<string, string>|null
     */
    public function decode(ClassMetadata $metadata, string $id): ?array;
}
