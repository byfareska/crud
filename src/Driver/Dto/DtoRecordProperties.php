<?php

declare(strict_types=1);

namespace Byfareska\Crud\Driver\Dto;

/**
 * What a record a use case returns is by property - what the list shows, the details print and the edit form starts
 * with. By default (none aliased) DtoRecords::propertiesOf(): an array as it is, of an object its public properties
 * and what its getters say. Alias it for a read model whose getters must not all be called - an entity of Doctrine
 * with lazy collections, a getter with a side effect - e.g. the normalizer of Symfony Serializer with groups:
 *
 *     #[AsAlias(DtoRecordProperties::class)]
 *     final readonly class SerializedProperties implements DtoRecordProperties
 *     {
 *         public function __construct(private NormalizerInterface $normalizer) {}
 *
 *         public function of(mixed $record): array
 *         {
 *             return (array) $this->normalizer->normalize($record, null, ['groups' => ['panel']]);
 *         }
 *     }
 */
interface DtoRecordProperties
{
    /**
     * @return array<string, mixed>
     */
    public function of(mixed $record): array;
}
